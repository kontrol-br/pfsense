# Codex prompt for FreeBSD-src RELENG_2_7_2

Copy the prompt below into Codex while its working directory is the root of the
`kontrol-br/FreeBSD-src` repository on branch `RELENG_2_7_2`.

```text
Estamos mantendo o repositório https://github.com/kontrol-br/FreeBSD-src.git,
branch RELENG_2_7_2, usado como base do Kontrol 2.7.2 e para criar o jail do
Poudriere.

Objetivo
========

Prepare este branch exclusivamente para builds amd64 de 64 bits do Kontrol.
Não construiremos imagens, kernels, world, bibliotecas de compatibilidade ou
native-xtools para ARM, armv7, aarch64, i386 ou qualquer outro alvo de 32 bits.
Faça uma correção mínima, auditável e compatível com os scripts de release e
com o Poudriere. Não remova diretórios de arquitetura da árvore do FreeBSD e não
faça uma limpeza ampla do código upstream: TARGET/TARGET_ARCH e as opções de
src.conf devem selecionar amd64; apagar fontes compartilhadas ou de outras
arquiteturas pode quebrar ferramentas de geração, targets do make e futuros
merges.

Problema observado
==================

Ao criar o jail amd64 com `poudriere jail -c ... -a amd64 -m git`, o buildworld
entrou em `stage 4.3.1: building lib32 shim libraries` e falhou em:

    make[4]: don't know how to make aes-586.S. Stop
    *** [build32] Error code 2

No branch atual, `secure/lib/libcrypto/Makefile` referencia `aes-586.S` quando
`ASM_i386` está ativo, mas `sys/crypto/openssl/i386/aes-586.S` não está
rastreado. Os warnings anteriores de libmd e OpenZFS não são a causa fatal.
Também já existe `WITHOUT_LIB32=YES` em `release/conf/Kontrol_src.conf`, mas o
log demonstra que a criação do jail não está usando efetivamente essa opção.

Tarefas obrigatórias
====================

1. Leia todos os AGENTS.md aplicáveis antes de editar e examine o histórico
   recente do branch.
2. Confirme os fatos com comandos, incluindo:
   - `git branch --show-current` e `git rev-parse HEAD`;
   - busca por `WITHOUT_LIB32`, `WITH_LIB32`, `TARGET`, `TARGET_ARCH`,
     `aes-586.S`, `ASM_i386` e pelas configurações Kontrol;
   - `git ls-files sys/crypto/openssl/i386/aes-586.S`;
   - inspeção de `release/conf/Kontrol_src.conf`, dos scripts de release e dos
     Makefiles envolvidos.
3. Identifique qual configuração deste repositório é realmente consumida pelos
   builds do Kontrol. Preserve `TARGET=amd64` e `TARGET_ARCH=amd64` nos pontos
   apropriados e garanta `WITHOUT_LIB32=yes` no src.conf efetivamente passado a
   buildworld/installworld. Não invente flags de make: confirme cada knob na
   infraestrutura `share/mk`, `tools/build/options` ou documentação presente na
   própria árvore.
4. Não confunda a configuração do release com a do Poudriere: o método git do
   Poudriere normalmente monta seu próprio `<jail>-src.conf` a partir de
   `/usr/local/etc/poudriere.d`. Se não houver como este repositório obrigar o
   consumidor a usar `release/conf/Kontrol_src.conf`, documente claramente essa
   fronteira e forneça o nome e conteúdo exatos do arquivo externo necessário:

       /usr/local/etc/poudriere.d/Kontrol_v2_7_2_amd64-src.conf
       WITHOUT_LIB32=yes

   Não alegue que uma alteração no FreeBSD-src, sozinha, modifica o
   comportamento do Poudriere se o arquivo não for lido por ele.
5. Mantenha a árvore de fontes genérica. Não delete `sys/arm`, `sys/arm64`,
   `sys/i386`, arquivos OpenSSL i386 ou listas de targets. Para este produto,
   deixar de construir outros alvos é uma decisão de configuração, não uma
   remoção de código upstream.
6. Trate `aes-586.S` de uma destas formas, justificando a escolha:
   - se todos os caminhos suportados passarem corretamente `WITHOUT_LIB32`,
     confirme que o arquivo deixa de ser requisito para o build amd64 do
     produto e não adicione um artefato não usado apenas para mascarar uma
     configuração incorreta;
   - se o branch deve continuar capaz de executar um buildworld FreeBSD padrão
     com lib32, gere `aes-586.S` com o `Makefile.asm` e as fontes OpenSSL deste
     mesmo commit, coloque-o em `sys/crypto/openssl/i386/`, revise o arquivo
     gerado e o inclua em um commit separado. Não copie silenciosamente um
     assembly de outra versão do OpenSSL.
7. Procure nos scripts por loops/listas que disparem builds ARM ou 32-bit. Só os
   altere se forem caminhos específicos do Kontrol e estiver comprovado que são
   usados neste branch. Preserve defaults upstream e permita override explícito
   sempre que possível.
8. Adicione documentação curta, próxima da configuração alterada, explicando:
   - que o release Kontrol é amd64-only;
   - por que lib32 está desativado;
   - qual configuração externa o Poudriere precisa consumir;
   - como verificar que o build não entrou no target `build32`.

Restrições
==========

- Não compile ARM, aarch64, armv7 ou i386.
- Não execute um buildworld completo sem antes informar o custo e verificar os
  recursos do ambiente.
- Não use `WITHOUT_OPENSSL`, `WITHOUT_CRYPT` ou uma desativação ampla de
  segurança como atalho.
- Não desative warnings globalmente e não edite o OpenSSL para esconder o erro.
- Não altere a ABI amd64 nem remova suporte necessário a ports amd64.
- Não faça mudanças no repositório pfsense/Kontrol a partir deste checkout.
- Não faça push forçado nem reescreva o histórico do branch.

Validação mínima
================

Execute verificações rápidas e determinísticas antes de qualquer compilação
longa:

1. `git diff --check`.
2. Mostre, por meio do make/configuração aplicável, que TARGET e TARGET_ARCH são
   amd64 e que MK_LIB32/WITHOUT_LIB32 resulta em lib32 desativado.
3. Faça um dry-run ou consulta de targets/variáveis que demonstre que `build32`
   não será selecionado. Não considere apenas um grep no arquivo de configuração
   como prova; confirme que o arquivo é efetivamente consumido.
4. Rode os testes de sintaxe disponíveis para qualquer shell script alterado.
5. Se gerar `aes-586.S`, demonstre que ele foi produzido das fontes OpenSSL do
   branch atual e execute as verificações específicas do Makefile sem iniciar
   builds de outras arquiteturas.
6. Apresente `git status --short`, o diff final, riscos residuais e os comandos
   exatos que o operador deverá executar no host FreeBSD/Poudriere.

Entrega
=======

Implemente a solução, não apenas descreva sugestões. Faça commits pequenos com
mensagens claras. No resumo final, separe explicitamente:

- mudanças dentro do FreeBSD-src;
- configuração externa exigida pelo Poudriere;
- testes realmente executados;
- testes não executados por limitação de ambiente;
- hash do commit do FreeBSD-src que deverá ser fixado pelo processo de release.
```
