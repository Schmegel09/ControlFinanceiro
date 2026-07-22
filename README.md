# Controle Financeiro

Sistema web de controle financeiro desenvolvido em PHP puro com autenticação segura, recuperação de senha via e-mail e interface moderna.

## 🚀 Funcionalidades

- ✅ **Autenticação segura** - Login e registro com senhas hasheadas
- ✅ **Recuperação de senha** - Fluxo em 3 etapas (email → código → nova senha) consolidado em uma única página
- ✅ **E-mail com PHPMailer** - Envio seguro via SMTP (Hostinger)
- ✅ **Interface moderna** - Design responsivo com gradientes e transições
- ✅ **Proteção de rotas** - Páginas protegidas com sessão de usuário
- ✅ **Importação CSV** - Movimentações em arquivos separados por `;` ou `,`
- ✅ **Carteiras pessoal e do casal** - Dados privados separados de uma carteira compartilhada por duas pessoas
- ✅ **Presença compartilhada** - Indica quando a outra pessoa está usando a carteira do casal
- ✅ **Gráficos no Dashboard** - Evolução financeira e distribuição de despesas por categoria
- ✅ **Escalável** - Estrutura modular com routing customizado

## 📋 Pré-requisitos

- PHP 8.0+
- MySQL 5.7+
- Composer
- Conta SMTP (ex: Hostinger)

## 🛠️ Instalação

1. **Clone o repositório**
```bash
git clone https://github.com/Schmegel09/ControlFinanceiro.git
cd ControlFinanceiro
```

2. **Instale as dependências**
```bash
composer install
```

3. **Configure o arquivo `.env`**
```bash
cp .env.example .env  # se existir
# ou crie manualmente
```

4. **Preencha o `.env` com suas credenciais**
```env
# Database
DB_HOST=seu_host
DB_PORT=3306
DB_NAME=seu_banco
DB_USER=seu_user
DB_PASSWORD=sua_senha

# URL pública usada nos links de confirmação
APP_URL=https://controlefinanceiro.seudominio.com

# SMTP
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USERNAME=seu_email@seu_dominio.com
SMTP_PASSWORD=sua_senha_smtp
SMTP_FROM_EMAIL=seu_email@seu_dominio.com
SMTP_FROM_NAME="Controle Financeiro"

# Administração SaaS (separe múltiplos e-mails por vírgula)
SUPERADMIN_EMAILS=seu_email@seu_dominio.com
```

5. **Crie o banco de dados**
```sql
CREATE TABLE `Usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `senha` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NOT NULL,
  `codigo` VARCHAR(6) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `tentativas` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (codigo),
  FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

6. **Configuração no Hostinger**

- Coloque todos os arquivos e pastas do projeto dentro da pasta `public_html` do seu domínio.
- Garanta que `.htaccess` esteja no mesmo nível do front controller (`index.php`).
- No Hostinger, configure o `.env` com as credenciais do banco de dados e SMTP para `controlefinanceiro.gmwebdigital.com`.
- Se você estiver usando o gerenciador de arquivos do Hostinger, crie `.env` manualmente a partir de `.env.example`.

7. **Inicie o servidor localmente**
```bash
php -S localhost:8000 router.php
```

7. **Acesse no navegador**
```
http://localhost:8000
```

## 📁 Estrutura do Projeto

```
ControlFinanceiro/
├── assets/
│   └── css/                # Estilos externos globais, componentes e páginas
├── app/
│   ├── Controllers/        # Fluxo das requisições e respostas
│   ├── Models/             # Consultas e persistência no banco
│   ├── Services/           # Regras de negócio financeiras
│   ├── Core/               # Sessão, autenticação e proteção interna
│   └── Views/              # HTML e componentes visuais
├── config/             # Configurações
│   ├── conexao.php     # Conexão com banco de dados
│   └── email.php       # Configuração de e-mail
├── routes/             # Definição de rotas
│   └── web.php
├── index.php           # Front controller / dispatcher principal
├── api.php             # Front controller da API
├── router.php          # Roteador do servidor PHP local
├── composer.json       # Dependências
└── .env               # Variáveis de ambiente
```

### Fluxo MVC

1. `index.php` resolve a URL usando `routes/web.php`.
2. A rota carrega um arquivo de `app/Controllers`.
3. O controlador valida a entrada e chama `Models` ou `Services`.
4. O controlador entrega os dados para uma view em `app/Views`.

As views não acessam o banco, não processam formulários e não fazem redirecionamentos.
Os estilos também ficam fora das views: `assets/css/base.css` concentra regras
globais, `assets/css/components.css` reúne componentes reutilizáveis e
`assets/css/pages/` mantém somente as regras específicas de cada tela.

No primeiro acesso autenticado, o sistema cria automaticamente a carteira pessoal
e associa a ela as categorias e movimentações antigas do usuário. Pela rota
`/carteiras`, uma pessoa pode criar uma carteira do casal e adicionar uma segunda
conta já cadastrada pelo e-mail. Cada integrante continua com sua carteira pessoal
privada e ambos passam a acessar somente os dados inseridos na carteira compartilhada.

### Controle SaaS e bloqueio de clientes

A variável `SUPERADMIN_EMAILS` define quais contas podem acessar `/admin-clientes`.
O painel permite associar um domínio ao cliente, configurar vencimento e tolerância,
aprovar cadastros, renovar assinaturas e alterar o status entre `pendente`, `ativo`,
`em_atraso`, `bloqueado` e `cancelado`.

O mesmo painel permite promover usuários cadastrados à função de superadministrador
ou remover essa função. O cadastro público nunca recebe essa permissão. A conta atual
não pode remover a própria função, e contas listadas em `SUPERADMIN_EMAILS` ficam
protegidas como acesso de emergência. Mudanças de função são registradas em
`superadmin_auditoria` e passam a valer na requisição seguinte.

Cada cliente também possui permissões independentes para Dashboard, Movimentações,
Categorias, Relatórios e Carteiras. O superadministrador escolhe as telas no formulário
do cliente. A regra é aplicada no menu, no acesso direto pela URL e nas chamadas da API.
Clientes que já existiam quando a estrutura foi criada mantêm todas as telas liberadas;
novos cadastros começam sem telas até que o administrador defina o acesso.

Os cartões de clientes oferecem ações rápidas para liberar, marcar atraso, bloquear ou
cancelar uma assinatura sem salvar o formulário completo. Essas ações também geram
registros em `cliente_auditoria`.

Usuários existentes são migrados automaticamente como ativos e com e-mail confirmado.
Novos cadastros recebem um link com validade de 24 horas e ficam pendentes até a
confirmação do e-mail e a aprovação do superadministrador. Depois do vencimento, o cliente
permanece com acesso durante a tolerância configurada; ao final dela, páginas e API
são bloqueadas sem exclusão dos dados. Todas as alterações administrativas ficam
registradas em `cliente_auditoria`.

O logout não precisa de uma view: a rota `/logout` aponta para
`app/Controllers/LogoutController.php`, que encerra a sessão e redireciona para `/login`.

## 🔑 Fluxo de Recuperação de Senha

1. **Etapa 1**: Usuário insere e-mail → código de 6 dígitos é enviado
2. **Etapa 2**: Usuário valida o código recebido
3. **Etapa 3**: Usuário define nova senha e confirma

Tudo acontece na rota `/recuperar-senha`, coordenada pelo `RecuperarSenhaController`.

## 📄 Importação CSV

A aba Movimentações aceita arquivos `.csv` e `.txt` com até 5 MB e 1.000 linhas.
O separador é identificado automaticamente e pode ser ponto e vírgula ou vírgula.
O botão **Baixar modelo CSV** gera um arquivo com cabeçalho e linhas de exemplo
compatíveis com Excel; substitua os exemplos pelos lançamentos reais antes de importar.

```csv
data;tipo;valor;categoria;descricao;parcelas
16/07/2026;despesa;1.234,56;Mercado;Compra do mês;1
17/07/2026;receita;2.000,00;Salário;Pagamento;1
```

As colunas `data` e `valor` são obrigatórias. Quando `tipo` não estiver presente,
valores negativos são despesas e valores positivos são receitas. Categorias ausentes
são criadas automaticamente.

## 🔐 Segurança

- Senhas armazenadas com `password_hash()` (bcrypt)
- Códigos de recuperação com expiração de 15 minutos
- Prepared statements para prevenir SQL injection
- CSRF protection com validação de sessão
- Arquivos internos de `app`, `config`, `routes` e `vendor` bloqueados pelo `.htaccess`

## 📧 E-mail

O sistema utiliza **PHPMailer 7.1.1** com fallback para `mail()` nativa do PHP.

- **Host**: smtp.hostinger.com (customizável)
- **Porta**: 587 (TLS)
- **Autenticação**: User/Password
- **Debug**: Desativado em produção (ativar SMTPDebug=2 em `config/email.php` se necessário)

## 🎨 Design

Interface moderna com:
- Gradiente roxo (#667eea → #764ba2)
- Transições suaves
- Responsivo para mobile/tablet/desktop
- Acessibilidade básica

## 📝 Rotas Disponíveis

| Rota | Proteção | Descrição |
|------|----------|-----------|
| `/` | Não | Redireciona para login |
| `/login` | Não | Página de login |
| `/cadastro` | Não | Página de registro |
| `/recuperar-senha` | Não | Recuperação de senha (3 etapas) |
| `/verificar-email` | Não | Confirmação e reenvio do link de e-mail |
| `/dashboard` | Sim | Dashboard do usuário |
| `/movimentacoes` | Sim | CRUD de movimentações |
| `/categorias` | Sim | CRUD de categorias |
| `/relatorios` | Sim | Relatórios por período |
| `/carteiras` | Sim | Seleção e compartilhamento de carteiras |
| `/assinatura-bloqueada` | Sim | Situação da assinatura sem acesso aos dados |
| `/admin-clientes` | Superadmin | Aprovação, renovação e bloqueio de clientes |
| `/logout` | Sim | Logout e limpeza de sessão |

## 🐛 Troubleshooting

**Emails não chegando?**
- Verificar credenciais SMTP no `.env`
- Verificar pasta Spam/Promoções do e-mail
- Ativar SMTPDebug=2 em `config/email.php` para diagnosticar

**Código não valida?**
- Confirmar que o código foi copiado corretamente (6 dígitos)
- Verificar se o código não expirou (15 minutos)
- Limpar cache/cookies do navegador

**Conexão com banco falha?**
- Validar credenciais do DB em `.env`
- Confirmar servidor MySQL está ativo
- Verificar se o banco e tabelas foram criados

## 🚀 Deploy

Para produção:
1. Desabilitar debug mode
2. Configurar SMTP_HOST correto
3. Usar HTTPS em produção
4. Adicionar rate-limiting para login/recuperação
5. Implementar logs de auditoria
6. Adicionar CSRF tokens adicionais

## 📄 Licença

Código aberto sob licença MIT.

## 👨‍💻 Autor

Gabriel Moreira - [GitHub](https://github.com/Schmegel09)

---

**Última atualização**: 16 de julho de 2026
