# Building Kontrol RELENG_2_7_2

These notes describe the host preparation needed before running `build.sh` on
FreeBSD 14, with particular attention to preserving the dependency versions in
`composer.lock`.

## Composer prerequisites

The PHP CLI used by Composer must provide the DOM, tokenizer, XML, XMLReader,
and XMLWriter extensions required by the locked dependencies. FreeBSD packages
these as separate PHP 8.2 modules, so install all of the corresponding binary
packages:

```sh
pkg install php82-dom php82-tokenizer php82-xml php82-xmlreader php82-xmlwriter
```

Do not work around missing extensions with `--ignore-platform-req`. Doing so
only skips Composer's host check; it does not make the extensions available to
the installed application or its tools.

Confirm that the CLI process is loading the expected PHP 8.2 configuration and
modules:

```sh
php --version
php --ini
php -m | egrep -i '^(dom|tokenizer|xml|xmlreader|xmlwriter)$'
```

The last command must print all five module names (the check is
case-insensitive because their displayed capitalization can vary).
If a package is installed but its module is absent, inspect
`/usr/local/etc/php/` and make sure Composer and `php --ini` resolve to the same
PHP executable and configuration tree.

The package repository configured on the build host must contain PHP 8.2
packages. Check availability before changing repository configuration:

```sh
pkg search -x 'php82-(dom|tokenizer|xml|xmlreader|xmlwriter)'
pkg info php82 php82-dom php82-tokenizer php82-xml php82-xmlreader php82-xmlwriter
```

Avoid mixing extensions from a different PHP minor version. If `pkg` proposes
replacing `php82` with a newer PHP branch, fix or pin the repository used for
the RELENG_2_7_2 build rather than accepting a mixed PHP installation.

## Install the locked dependencies

Use `install`, rather than `update`, for a reproducible release build:

```sh
cd /usr/Kontrol
composer install --prefer-dist --no-interaction
composer check-platform-reqs --lock
```

If Composer reports `ext-xmlreader` or `ext-xmlwriter` as missing after the
first prerequisite installation, install the two separately packaged modules
and retry the same locked install:

```sh
pkg install php82-xmlreader php82-xmlwriter
php -m | egrep -i '^(xmlreader|xmlwriter)$'
composer install --prefer-dist --no-interaction
```

This is another host prerequisite error, not evidence that the versions in the
lock file are incompatible. Do not run `composer update` in response to it.

`composer install` consumes the exact versions and references recorded in
`composer.lock`. In contrast, `composer update` resolves the version ranges in
`composer.json` again and can replace dependencies with releases that were not
used for RELENG_2_7_2.

Composer may warn that the lock file's content hash does not match
`composer.json`. Treat that warning separately from the missing-extension
error: do not run an unrestricted update merely to silence it. First use:

```sh
composer validate --no-check-publish
git diff -- composer.json composer.lock
```

If a lock-file refresh is intentionally required, perform it in a dedicated
change, review the complete `composer.lock` diff, and test the resulting build.
Do not combine that dependency change with host bootstrap troubleshooting.

## Build sequence

After Composer completes successfully, the usual RELENG_2_7_2 sequence is:

```sh
cd /usr/Kontrol
time ./build.sh --setup-poudriere
./build.sh --update-poudriere-ports
time ./build.sh --update-pkg-repo
time ./build.sh --build-kernels
time ./build.sh -i iso
```

The kernel-only step is optional as a diagnostic, but it is useful for
separating base/kernel failures from ports and package failures. Save the full
output of each step. When an old port fails, record the failing origin, package
version, poudriere jail name, ports-tree revision, and the first actual compiler
or fetch error rather than only the final `build failed` summary.

## Poudriere fails building `aes-586.S`

To delegate the corresponding source-tree work safely, use the prepared
[Codex prompt for the FreeBSD-src repository](CODEX_PROMPT_FREEBSD_SRC_RELENG_2_7_2.md).

The following failure is not caused by the preceding `libmd` or OpenZFS
warnings:

```text
make[4]: don't know how to make aes-586.S. Stop
*** [build32] Error code 2
```

The fatal error occurs while building the i386 compatibility libraries for an
amd64 jail. On the `RELENG_2_7_2` FreeBSD source branch,
`secure/lib/libcrypto/Makefile` includes `aes-586.S` in the i386 assembly
sources, and its `.PATH` expects the generated file under
`sys/crypto/openssl/i386`. If that file is absent, `make` cannot construct
`libcrypto` and the later `buildworld` and jail errors are only consequences.

### Confirm the source-tree inconsistency

Check the remote branch independently of the temporary jail, because Poudriere
removes the failed jail during cleanup:

```sh
git clone --depth 1 --branch RELENG_2_7_2 \
  https://github.com/kontrol-br/FreeBSD-src.git /usr/FreeBSD-src-RELENG_2_7_2
cd /usr/FreeBSD-src-RELENG_2_7_2
grep -n 'aes-586.S' secure/lib/libcrypto/Makefile
git ls-files sys/crypto/openssl/i386/aes-586.S
test -f sys/crypto/openssl/i386/aes-586.S
```

The `grep` command finding a reference while the last two checks produce no
file confirms the incomplete source tree. Re-running `--setup-poudriere`
against the unchanged remote branch will reproduce the same failure.

### Immediate release-compatible workaround

The Kontrol source configuration already disables 32-bit compatibility
libraries. The build scripts now tell Poudriere the same thing automatically
by creating the jail-specific source configuration. After updating this
repository, retry directly:

```sh
cd /usr/Kontrol
git pull --ff-only
time ./build.sh --setup-poudriere
```

For an older checkout without that build-script change, create the equivalent
configuration manually before retrying:

```sh
mkdir -p /usr/local/etc/poudriere.d
printf 'WITHOUT_LIB32=yes\n' \
  > /usr/local/etc/poudriere.d/Kontrol_v2_7_2_amd64-src.conf
cd /usr/Kontrol
time ./build.sh --setup-poudriere
```

Poudriere reads a jail-specific `<jail>-src.conf` while running `buildworld`.
Verify in the new log that it no longer enters `stage 4.3.1: building lib32
shim libraries`. This workaround is appropriate only while the product remains
configured with `WITHOUT_LIB32`; remove it if 32-bit ABI support becomes a
release requirement.

The scripts also default the Poudriere architecture list to `amd64.amd64`.
Installing qemu-user-static no longer silently adds an ARM jail; `arm.armv7`
remains available only through an explicit `ARCH_LIST` override on a properly
configured development host.

### Permanent source fix

The source repository should still be made internally buildable. Generate the
missing assembly from the OpenSSL sources already present on the same branch,
rather than copying an arbitrary generated file from a different OpenSSL
version:

```sh
cd /usr/FreeBSD-src-RELENG_2_7_2
cd secure/lib/libcrypto
make TARGET=i386 TARGET_ARCH=i386 -f Makefile.asm aes-586.S
install -m 0644 aes-586.S ../../../sys/crypto/openssl/i386/aes-586.S
rm -f aes-586.S
cd ../../..
git status --short
git diff --check
git add sys/crypto/openssl/i386/aes-586.S
git commit -m 'libcrypto: restore generated i386 AES assembly'
git push origin RELENG_2_7_2
```

Review the generated file and test `buildworld` before pushing. Once the fixed
commit is visible on the remote branch, remove the failed Poudriere jail if it
still exists and rerun `./build.sh --setup-poudriere`. Record the source commit
used by the successful jail so future rebuilds do not silently move to a
different branch tip.
