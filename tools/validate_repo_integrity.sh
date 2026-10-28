#!/bin/sh

# Abort publication when packagesite.pkg references a missing, truncated, or
# different package.  Only FreeBSD base utilities are used so this can run on
# a clean Poudriere builder.

set -eu

usage() {
	echo "usage: ${0##*/} [-u] repository" >&2
	echo "  -u  allow an unsigned development repository" >&2
	exit 64
}

require_signature=1
while getopts "u" option; do
	case "${option}" in
	u) require_signature="" ;;
	*) usage ;;
	esac
done
shift $((OPTIND - 1))
[ "$#" -eq 1 ] || usage
repository=$1
[ -d "${repository}/All" ] || { echo "package directory not found: ${repository}/All" >&2; exit 66; }
[ -f "${repository}/packagesite.pkg" ] || { echo "catalog not found: ${repository}/packagesite.pkg" >&2; exit 66; }
repository=$(CDPATH= cd -- "${repository}" && pwd -P)

workdir=$(mktemp -d "${TMPDIR:-/tmp}/kontrol-repo-validate.XXXXXX")
trap 'rm -rf "${workdir}"' EXIT HUP INT TERM
catalog="${workdir}/packagesite.yaml"
manifest="${workdir}/packages.manifest"
archive_members="${workdir}/packagesite.members"

tar -tf "${repository}/packagesite.pkg" > "${archive_members}" && \
    tar -xOf "${repository}/packagesite.pkg" packagesite.yaml > "${catalog}" || {
	echo "unable to extract packagesite.yaml" >&2
	exit 65
}
if [ -n "${require_signature}" ]; then
	for member in packagesite.yaml.sig packagesite.yaml.pub; do
		grep -qx "${member}" "${archive_members}" || {
			echo "unsigned repository catalog: missing ${member}" >&2
			exit 65
		}
	done
fi

awk '
function string_value(key, value) {
	pattern = "\"" key "\"[[:space:]]*:[[:space:]]*\""
	if (!match($0, pattern)) return ""
	value = substr($0, RSTART + RLENGTH)
	sub(/".*/, "", value)
	return value
}
function number_value(key, value) {
	pattern = "\"" key "\"[[:space:]]*:[[:space:]]*"
	if (!match($0, pattern)) return ""
	value = substr($0, RSTART + RLENGTH)
	sub(/[^0-9].*/, "", value)
	return value
}
NF {
	path = string_value("path")
	if (path == "") path = string_value("repopath")
	sum = string_value("sum")
	size = number_value("pkgsize")
	if (path == "" || sum == "" || size == "") {
		printf "packagesite.yaml:%d: missing path, pkgsize, or sum\n", NR > "/dev/stderr"
		exit 1
	}
	printf "%s\t%s\t%s\n", sum, size, path
}
' "${catalog}" > "${manifest}" || exit 65

[ -s "${manifest}" ] || { echo "repository catalog is empty" >&2; exit 65; }
duplicate=$(cut -f3 "${manifest}" | LC_ALL=C sort | uniq -d | head -1)
[ -z "${duplicate}" ] || { echo "duplicate catalog path: ${duplicate}" >&2; exit 65; }

tab=$(printf '\t')
failures=0
count=0
while IFS="${tab}" read -r expected_hash expected_size relative; do
	count=$((count + 1))
	case "${relative}" in
	All/*.pkg)
		package_name=${relative#All/}
		case "${package_name}" in
		""|*/*|.|..) echo "unsafe package path: ${relative}" >&2; failures=$((failures + 1)); continue ;;
		esac
		;;
	*) echo "unsafe package path: ${relative}" >&2; failures=$((failures + 1)); continue ;;
	esac
	case "${expected_size}" in
	""|*[!0-9]*) echo "invalid pkgsize: ${relative}" >&2; failures=$((failures + 1)); continue ;;
	esac
	if printf '%s\n' "${expected_hash}" | grep -Eq '^[0-9A-Fa-f]{64}$'; then
		hash_type=sha256_hex_raw
	elif printf '%s\n' "${expected_hash}" | grep -Eq '^1\$[0-9A-Fa-f]{64}$'; then
		hash_type=sha256_hex
	elif printf '%s\n' "${expected_hash}" | grep -Eq '^2\$[a-z0-9]+$'; then
		hash_type=blake2_base32
	else
		echo "invalid package checksum: ${relative}" >&2
		failures=$((failures + 1))
		continue
	fi
	package="${repository}/${relative}"
	if [ ! -f "${package}" ]; then
		echo "missing: ${relative}" >&2
		failures=$((failures + 1))
		continue
	fi
	actual_size=$(stat -f %z "${package}")
	if [ "${actual_size}" != "${expected_size}" ]; then
		echo "size mismatch: ${relative}: catalog=${expected_size}, file=${actual_size}" >&2
		failures=$((failures + 1))
		continue
	fi
	case "${hash_type}" in
	sha256_hex_raw) actual_hash=$(sha256 -q "${package}") ;;
	sha256_hex) actual_hash=$(pkg-static checksum -q -t sha256_hex "${package}") ;;
	blake2_base32) actual_hash=$(pkg-static checksum -q -t blake2_base32 "${package}") ;;
	esac
	if [ "${actual_hash}" != "${expected_hash}" ]; then
		echo "package checksum mismatch: ${relative}: catalog=${expected_hash}, file=${actual_hash}" >&2
		failures=$((failures + 1))
	fi
done < "${manifest}"

[ "${failures}" -eq 0 ] || {
	echo "repository validation failed: ${failures} catalog/file divergence(s)" >&2
	exit 1
}
echo "repository integrity OK: ${count} package(s)"
