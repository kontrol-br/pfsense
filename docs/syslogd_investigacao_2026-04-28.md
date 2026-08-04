# Investigação: syslogd parando após dias/semanas

Data: 2026-04-28

## Escopo
- Verificar se o commit `383bbb6361ccbf95aacc5be8666c970dacda7451` corrige parada do `syslogd` ou se é apenas ajuste cosmético/funcional de UI.
- Comparar o fluxo do serviço `syslogd` no código local vs. `pfsense/pfsense` branch `master`.

## Resultado curto
- O commit `383bbb6` **não altera ciclo de vida do processo `syslogd`** (start/stop/restart, pidfile, sinais, rc scripts).
- As mudanças de `383bbb6` são de **correção de lookup de regra de firewall nos logs** (rulenum/subrulenum/tracker) em telas de log e widget.
- Portanto, esse commit **não deveria resolver** um problema de `syslogd` morrendo após dias/semanas.

## Evidências do commit 383bbb6
Arquivos alterados no commit:
- `src/etc/inc/syslog.inc`
- `src/usr/local/www/status_logs_filter.php`
- `src/usr/local/www/status_logs_filter_dynamic.php`
- `src/usr/local/www/widgets/widgets/log.widget.php`

Natureza das alterações:
- Assinatura de função e chamadas de lookup de regra passaram a usar `subrulenum` além de `rulenum`.
- Links AJAX de “mostrar regra” foram atualizados para enviar 4 parâmetros (`rulenum,subrulenum,tracker,act`).
- Ajuste de mapeamento no widget para casar por `subrulenum` quando existir.

Não houve alteração em:
- comando `/usr/sbin/syslogd ...`
- `sigkillbypid(..., TERM|KILL|HUP)`
- arquivos de pid (`/var/run/syslog.pid`)
- lógica de boot/serviço do `syslogd`

## Comparativo local vs upstream master (pfsense)
### Diferença relevante encontrada no start do syslogd
No código local, `system_syslogd_start()` usa tentativa em múltiplos comandos e valida pid:
1. tenta comando completo com formato e sockets
2. tenta sem formato
3. tenta modo compatível
4. fallback síncrono se pid não aparecer
5. registra erro se falhar

No upstream master, a inicialização é direta com um `mwexec_bg()` único (com flags legadas `-c -c`).

Interpretação:
- A versão local está **mais resiliente para START** (especialmente boot/race), não menos.
- Isso **não explica** parada espontânea depois de vários dias, pois esse trecho só roda em start/restart/HUP.

## Fluxo atual do serviço e ponto de fragilidade
- O boot chama `system_syslogd_start()`.
- Há chamadas ocasionais de restart/HUP em eventos de configuração.
- Porém, não há watchdog dedicado no core que faça “keepalive” contínuo do `syslogd` em intervalos curtos.
- Se o processo morrer depois de dias por motivo externo (crash, kill, recurso), ele pode ficar parado até intervenção/manual/evento que reinicie.

## Hipóteses mais prováveis para “morre sem log”
1. **Crash do binário `/usr/sbin/syslogd`** (sem registro local porque justamente o logger caiu).
2. **Kill externo do processo** (OOM killer, script/ação operacional, signal indevido).
3. **Condição de ambiente** (storage/FS anômalo, recurso exaurido, corrupção transitória).
4. **Evento raro em rotação/reabertura de log** (janela de race ou estado inválido de pid/socket).

## Recomendações práticas
1. Implementar monitor simples periódico (cron) para garantir disponibilidade:
   - se pid inválido/processo ausente: reiniciar `system_syslogd_start()` e registrar evento alternativo.
2. Capturar evidências no momento da falha:
   - status de processo, pidfile, `dmesg`, memória, espaço em disco, estado de FS.
3. Registrar telemetria externa (remote syslog/monitor) para não depender só do logger local.
4. Se possível, habilitar coleta de core dump do `syslogd` e correlacionar com horário da queda.

## Conclusão
- `383bbb6` é uma correção funcional de apresentação/associação de regras de firewall nos logs, **não** de disponibilidade do daemon `syslogd`.
- A diferença local vs upstream no start tende a **melhorar robustez de inicialização**, não causar parada tardia.
- A investigação deve focar em causa externa/rara de runtime e em mecanismo de auto-recuperação (watchdog/health-check).
