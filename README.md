# SumbaneCredit — Sistema Inteligente de Gestão de Microcrédito

> *Crédito simples, gestão inteligente.*

Aplicação web Laravel (Blade + Bootstrap 5) para uma instituição de microcrédito em Moçambique: website institucional, portal do cliente e painel administrativo na mesma aplicação, com motor financeiro decimal, razão (ledger) imutável, auditoria, permissões por perfil, documentos PDF/Excel, notificações e tarefas agendadas.

Moeda: Metical (MZN) · Fuso horário: Africa/Maputo · Idioma: Português.

---

## 1. Requisitos

| Componente | Versão utilizada / mínima |
|---|---|
| PHP | 8.2 (testado com 8.2.12 do XAMPP) |
| Extensões PHP | `bcmath`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, **`gd`**, **`zip`**, `intl` |
| MySQL / MariaDB | MariaDB 10.4 (XAMPP) ou MySQL 8 — InnoDB |
| Composer | 2.x |
| Node.js / npm | apenas para copiar as bibliotecas de frontend (não há bundler) |
| Laravel | 12.x |

> No XAMPP, `gd`, `zip` e `intl` vêm comentadas em `C:\xampp\php\php.ini`. Retire o `;` das linhas `extension=gd`, `extension=zip` e `extension=intl` e reinicie o Apache.

Pacotes: `spatie/laravel-permission` 6, `barryvdh/laravel-dompdf` 3, `maatwebsite/excel` 3.1 (PhpSpreadsheet). Frontend: Bootstrap 5.3, Bootstrap Icons, Chart.js 4, SweetAlert2 — servidos localmente a partir de `public/vendor`.

## 2. Instalação (Windows + XAMPP)

```bat
cd C:\xampp\htdocs\SumbaneCredit
composer install
npm install            :: copia Bootstrap, ícones, Chart.js e SweetAlert2 para public\vendor
copy .env.example .env
php artisan key:generate
```

Criar as bases de dados (phpMyAdmin ou consola):

```sql
CREATE DATABASE sumbanecredit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE sumbanecredit_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; -- testes
```

Configurar o `.env` (`DB_DATABASE=sumbanecredit`, `DB_USERNAME`, `DB_PASSWORD`) e executar:

```bat
php artisan migrate --seed       :: estrutura + perfis/permissões + definições (+ demonstração se APP_ENV=local)
php artisan storage:link         :: opcional (os documentos são privados e não usam este link)
```

Recriar tudo com dados de demonstração (apenas local): `php artisan migrate:fresh --seed`.

> No MariaDB do XAMPP as migrations demoram 2–3 minutos (operações DDL lentas em Windows). É normal.

### Assets

Não há compilação: CSS e JS próprios estão em `public/css/app.css` e `public/js/app.js`. `npm run build` (ou `npm install`) apenas copia as bibliotecas para `public/vendor`.

## 3. Iniciar a aplicação

```bat
php artisan serve                 :: http://localhost:8000
php artisan queue:work            :: processa notificações (email/SMS) em fila — terminal separado
php artisan schedule:work         :: tarefas agendadas em desenvolvimento — terminal separado
```

Em produção, configure um *cron* (Linux) ou o Agendador de Tarefas (Windows) a executar `php artisan schedule:run` a cada minuto e um supervisor para `queue:work`.

Tarefas agendadas (Africa/Maputo):

| Hora | Comando | Função |
|---|---|---|
| 00:30 | `loans:process-overdue` | dias de atraso, penalizações contratuais (uma por prestação), abertura/encerramento de cobranças, avisos de atraso |
| 01:00 | `collections:check-promises` | marca promessas de pagamento como cumpridas/não cumpridas |
| 02:00 | `loans:reconcile` | verifica saldos × prestações × razão × pagamentos e regista diferenças |
| 08:00 | `loans:send-reminders` | avisos de próximas prestações (N dias antes, configurável) |

Todos aceitam `--date=AAAA-MM-DD` para reprocessar uma data.

### Alternativa: importar o ficheiro SQL

`database/sql/sumbanecredit.sql` contém o schema completo (estrutura + perfis/permissões + definições + dados de demonstração). Importe-o no phpMyAdmin (*Importar*) ou com `mysql -u root < database\sql\sumbanecredit.sql`. Cria a base `sumbanecredit` e não é preciso correr as migrations.

### Antes de hospedar: apagar os dados fictícios

```bash
php artisan sumbanecredit:purge-demo
php artisan sumbanecredit:create-admin
```

Apaga utilizadores, clientes, produtos, pedidos, empréstimos, pagamentos, auditoria e ficheiros carregados. Mantém a estrutura, os perfis e permissões, as definições, as perguntas frequentes e as regras de avaliação (que voltam a ficar por validar).

## 4. Primeiro administrador (produção)

Não use os dados de demonstração em produção. Com `APP_ENV=production`:

```bash
php artisan migrate --force
php artisan db:seed --force                 # perfis, permissões, definições (sem dados de demonstração)
php artisan sumbanecredit:create-admin      # pede nome, email e palavra-passe (oculta, mín. 12 caracteres)
```

O Super Administrador gere configuração, utilizadores e permissões, **mas não aprova crédito, não autoriza/executa desembolsos nem confirma/estorna pagamentos**. Crie no painel os restantes funcionários (recebem um email para definir a palavra-passe) e active a autenticação de dois factores (Conta › Segurança). Em *Definições › Segurança* pode tornar o 2FA obrigatório para funcionários.

## 5. Contas de demonstração (apenas `APP_ENV=local`)

Palavra-passe de todas: valor de `DEMO_PASSWORD` no `.env` (por omissão `Demo@2026!`).

| Perfil | Email |
|---|---|
| Super Administrador | superadmin@demo.sumbanecredit.local |
| Administrador | admin@demo.sumbanecredit.local |
| Gestor de Crédito | gestor@demo.sumbanecredit.local (e gestor2@…) |
| Agente de Crédito | agente@demo.sumbanecredit.local (e agente2@…) |
| Caixa | caixa@demo.sumbanecredit.local |
| Contabilista | contabilista@demo.sumbanecredit.local |
| Cliente | cliente@demo.sumbanecredit.local |

Os dados de demonstração (26 clientes fictícios, 4 produtos ilustrativos, pedidos em todas as fases, empréstimos liquidados/activos/em atraso/reestruturados, liquidação antecipada, estorno, cobranças, tickets) são criados **através dos serviços reais** com datas simuladas, pelo que o razão, as prestações e os saldos são coerentes. Todos os registos estão identificados como demonstração (`@demo.sumbanecredit.local`, `clients.is_demo = 1`, testemunhos marcados).

Em local, os emails são escritos em `storage/logs/laravel.log` (`MAIL_MAILER=log`) e os SMS apenas registados no log (`SMS_DRIVER=log`).

## 6. Testes

```bat
php artisan test
```

Os testes usam a base `sumbanecredit_test` (MySQL), definida em `phpunit.xml`. Cobrem: motor financeiro (arredondamentos, métodos, datas, encargos, imputação, liquidação, penalizações, avaliação), autenticação (login, logout, registo, verificação de email, recuperação de palavra-passe, limitação de tentativas, 2FA), permissões e segregação de funções, isolamento de dados entre clientes, fluxo completo de crédito, pagamentos (integral, parcial, antecipado, duplicados, idempotência, estornos, reconciliação, liquidação), tarefas agendadas e renderização de todas as páginas, PDF e Excel.

## 7. Arquitectura

```
app/
  Enums/                 Estados e domínios (pedido, empréstimo, pagamento, desembolso, prestação, método, periodicidade)
  Services/Finance/      Motor financeiro puro (sem base de dados): juros, amortização, encargos, datas,
                         imputação de pagamentos, liquidação, penalizações, avaliação de crédito
  Services/Credit/       Simulação, pedidos (máquina de estados), avaliação, produtos versionados
  Services/Loans/        Empréstimos, plano, desembolsos, saldos, penalizações, reestruturação, monitorização
  Services/Payments/     Pagamentos, liquidação, reconciliação, arquitectura de gateways (M-Pesa/e-Mola)
  Services/Collections/  Cobranças, contactos, promessas
  Services/Reports/      14 relatórios (ecrã, PDF, Excel)
  Services/Documents/    PDF (contrato, plano, recibo, desembolso, extracto, relatórios)
  Services/…             Auditoria, numeração sequencial, definições, ficheiros privados, razão, idempotência, notificações
  Http/Controllers/      PublicSite/, Auth/, Account/, Client/ (portal), Admin/ (painel)
  Http/Requests/         Validação (Form Requests)
  Policies/              Propriedade dos registos (cliente só vê os seus) e decisões
  Events/ Listeners/     Eventos de domínio (após commit) → notificações
  Notifications/         Base de dados + email (+ SMS opcional), em fila
resources/views/         layouts/, components/, public/, auth/, client/, admin/, loans/, pdf/, errors/
database/migrations/     Esquema completo (InnoDB, chaves estrangeiras, índices, unicidade)
tests/Unit, tests/Feature
```

### Regras financeiras (explícitas)

- **Aritmética**: `DECIMAL(15,2)` na base de dados, `bcmath` em PHP (`App\Support\Money`), arredondamento *meio para cima* a 2 casas. Nunca `float`.
- **Taxa**: nominal; conversão **proporcional** para o período (mensal = taxa anual/12, quinzenal = /26, semanal = /52; taxa mensal × 12 / períodos).
- **Métodos**: prestações constantes (juros sobre saldo), amortização constante (juros sobre saldo), juros simples sobre o capital inicial. Cada produto define os métodos permitidos.
- **Arredondamento**: componentes arredondados por prestação; a última prestação absorve as diferenças (soma do capital = capital financiado).
- **Datas**: 1.ª prestação um período após a data efectiva do desembolso; mensal no mesmo dia sem ultrapassar o fim do mês.
- **Encargos**: fixos ou % do capital; *deduzidos no desembolso* ou *financiados* (repartidos pelas prestações).
- **Imputação**: prestação mais antiga primeiro; em cada prestação penalizações → encargos → juros → capital (ordem configurável). Excedente antecipa prestações seguintes; valor acima do total em dívida é recusado.
- **Liquidação antecipada**: prestações vencidas integralmente + juros do período em curso + capital e encargos futuros; juros futuros dispensados ou devidos conforme o contrato.
- **Penalizações**: uma por prestação, após a carência, fixa ou % do valor em atraso, conforme o produto.
- **Estornos**: ordem inversa (LIFO); geram movimentos compensatórios e repõem as prestações.
- **Integridade**: transacções MySQL com `SELECT … FOR UPDATE`, índices únicos contra desembolsos/pagamentos/referências duplicados, chaves de idempotência por formulário, razão e auditoria imutáveis, reconciliação diária.

## 8. Dependências externas não configuradas

| Integração | Estado | O que falta |
|---|---|---|
| Email (SMTP) | `MAIL_MAILER=log` (não envia) | Servidor SMTP da instituição no `.env` |
| SMS | `SMS_DRIVER=log` (regista, não envia) | Contrato com prestador/agregador e implementação de `App\Services\Sms\SmsProvider` |
| M-Pesa / e-Mola | Desactivados; registo e confirmação manual | Contrato, credenciais e especificação oficial; implementar `ProviderGateway::initiate/parseCallback` e rota de *callback* |
| Filas | `QUEUE_CONNECTION=database` | Processo `queue:work` permanente (supervisor/serviço) |
| Agendador | — | `schedule:run` a cada minuto |

Nenhuma integração é simulada: sem configuração, o sistema indica claramente que o pagamento deve ser registado manualmente.

## 9. Conformidade em Moçambique — requisitos a validar

A implementação técnica **não constitui prova de autorização para conceder crédito**. Antes da utilização em produção, a instituição deve validar com assessoria jurídica/regulatória:

1. **Autorização da actividade** junto do Banco de Moçambique para a categoria de instituição (ex.: operador de microcrédito / microbanco) e respectivas obrigações de reporte. Preencher em *Definições › Instituição* a denominação legal, NUIT e referência da autorização — o sistema não as presume.
2. **Taxas, encargos e penalizações**: o sistema não impõe limites legais; confirmar se existem limites ou regras de divulgação aplicáveis (custo total do crédito, TAEG ou equivalente) e ajustar os produtos e o contrato.
3. **Identificação do cliente (KYC) e prevenção do branqueamento de capitais e financiamento do terrorismo**: o sistema regista documentos e verificação de identidade; procedimentos de diligência reforçada, listas de sanções, comunicação de operações suspeitas e limites de numerário devem ser definidos pela instituição.
4. **Protecção de dados pessoais**: rever a política de privacidade (modelo incluído), bases legais, prazos de conservação e direitos dos titulares face à legislação moçambicana aplicável.
5. **Contrato**: o modelo gerado é uma minuta técnica; o texto contratual definitivo deve ser elaborado/validado juridicamente. O PDF não contém assinatura digital nem garantia de validade jurídica.
6. **Conservação documental e auditoria**: confirmar prazos legais; o sistema não apaga movimentos financeiros nem registos de auditoria.
7. **Práticas de cobrança**: definir regras de contacto (horários, canais, contacto de terceiros/fiadores) conformes com a lei e a conduta de mercado.
8. **Informação financeira e reporte**: plano de contas e reportes regulatórios não estão incluídos; os relatórios e a exportação Excel servem de base.
9. **Central de riscos / informação de crédito**: consultas e reportes a centrais externas não estão integrados.

## 10. Limitações conhecidas para produção

- Integrações de pagamento, SMS e email reais por configurar (secção 8).
- Sem contabilidade geral (plano de contas, diário geral) — o razão é por empréstimo.
- Avaliação de crédito com regras configuráveis, **não** um modelo estatístico validado; a decisão é sempre humana.
- Calendário sem feriados/dias úteis: vencimentos podem calhar em fins-de-semana.
- Reestruturação gera novo plano sobre o capital em dívida; não suporta carências parciais (só de juros) nem planos irregulares.
- Pagamentos com sobrevalor são recusados (não há gestão de saldo credor/devolução).
- Os estornos seguem a ordem inversa dos pagamentos.
- Rever antes de produção: HTTPS (`SESSION_SECURE_COOKIE=true`), `APP_DEBUG=false`, cópias de segurança da base de dados e de `storage/app/private`, políticas de palavra-passe e 2FA obrigatório para funcionários.
