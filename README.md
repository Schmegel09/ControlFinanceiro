# Controle Financeiro

Sistema web de controle financeiro desenvolvido em PHP puro com autenticação segura, recuperação de senha via e-mail e interface moderna.

## 🚀 Funcionalidades

- ✅ **Autenticação segura** - Login e registro com senhas hasheadas
- ✅ **Recuperação de senha** - Fluxo em 3 etapas (email → código → nova senha) consolidado em uma única página
- ✅ **E-mail com PHPMailer** - Envio seguro via SMTP (Hostinger)
- ✅ **Interface moderna** - Design responsivo com gradientes e transições
- ✅ **Proteção de rotas** - Páginas protegidas com sessão de usuário
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

# SMTP
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USERNAME=seu_email@seu_dominio.com
SMTP_PASSWORD=sua_senha_smtp
SMTP_FROM_EMAIL=seu_email@seu_dominio.com
SMTP_FROM_NAME="Controle Financeiro"
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
php -S localhost:8000
```

7. **Acesse no navegador**
```
http://localhost:8000
```

## 📁 Estrutura do Projeto

```
ControlFinanceiro/
├── pages/              # Páginas da aplicação
│   ├── login.php
│   ├── cadastro.php
│   ├── dashboard.php
│   ├── recuperar_senha.php
│   └── logout.php
├── config/             # Configurações
│   ├── conexao.php     # Conexão com banco de dados
│   └── email.php       # Configuração de e-mail
├── routes/             # Definição de rotas
│   └── web.php
├── includes/           # Includes compartilhados
│   └── proteger.php
├── index.php           # Front controller / dispatcher principal
├── router.php          # Lógica de roteamento
├── composer.json       # Dependências
└── .env               # Variáveis de ambiente
```

## 🔑 Fluxo de Recuperação de Senha

1. **Etapa 1**: Usuário insere e-mail → código de 6 dígitos é enviado
2. **Etapa 2**: Usuário valida o código recebido
3. **Etapa 3**: Usuário define nova senha e confirma

Tudo acontece em uma única página (`recuperar_senha.php`) com transições suave entre etapas.

## 🔐 Segurança

- Senhas armazenadas com `password_hash()` (bcrypt)
- Códigos de recuperação com expiração de 15 minutos
- Prepared statements para prevenir SQL injection
- CSRF protection com validação de sessão
- Desabilitar verificação rigorosa de certificado SSL (para auto-assinados como Hostinger)

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
| `?page=login` | Não | Página de login |
| `?page=cadastro` | Não | Página de registro |
| `?page=recuperar-senha` | Não | Recuperação de senha (3 etapas) |
| `?page=dashboard` | Sim | Dashboard do usuário |
| `?page=logout` | Sim | Logout e limpeza de sessão |

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

**Última atualização**: 13 de julho de 2026
