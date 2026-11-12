# Adotaí

Site de adoção de animais em PHP e MySQL/MariaDB. Este projeto usa `mysqli` e mantém as páginas de detalhes dos animais como páginas normais.

Requisitos: PHP 8+ com as extensões `mysqli` e `fileinfo`, e MySQL/MariaDB.

## Rodar no Windows com XAMPP

1. Instale o [XAMPP](https://www.apachefriends.org/) com PHP 8+ e MySQL.
2. Baixe o ZIP do GitHub, extraia a pasta e coloque-a em `C:\xampp\htdocs\adotai`.
3. Abra o painel do XAMPP e inicie **Apache** e **MySQL**.
4. Acesse [phpMyAdmin](http://localhost/phpmyadmin), escolha **Importar**, selecione `C:\xampp\htdocs\adotai\database\instalacao.sql` e clique em **Importar**. O arquivo cria o banco `adotai_site` e inclui dados para demonstração.
5. Abra [http://localhost/adotai/](http://localhost/adotai/).

Importe `database/instalacao.sql` apenas uma vez e somente para uma instalação nova. Esse arquivo já cria as tabelas e aplica todas as alterações necessárias; não é preciso executar os outros arquivos de migração.

## Rodar sem Apache

Com PHP e MySQL instalados, importe o banco no Prompt de Comando do Windows, a partir da raiz do projeto:

```cmd
mysql -u root < database\instalacao.sql
```

Se o comando `mysql` não for encontrado, use o phpMyAdmin do XAMPP para importar o mesmo arquivo. Depois, inicie o MySQL e execute na raiz do projeto:

```powershell
php -S 127.0.0.1:8000 -t .
```

Depois, abra [http://127.0.0.1:8000](http://127.0.0.1:8000).

## Conexão com o banco

O padrão do XAMPP é `127.0.0.1:3306`, banco `adotai_site`, usuário `root` e senha vazia. Se a sua instalação usar outros dados, configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASS` no ambiente ou ajuste os valores padrão em `config/database.php`. Não publique senhas reais no GitHub.

## Atualizar uma instalação existente

Não importe `database/instalacao.sql` em um banco que já tenha dados: ele é para uma instalação nova. Faça backup e aplique, uma única vez e na ordem, `database/migracao_adotai.sql`, `database/migracao_contas_profissionais.sql` e `database/migracao_contatos_planos_profissionais.sql`.

## Primeiro administrador

Crie uma conta pelo site e, no PowerShell, promova-a a administrador. A senha temporária é solicitada sem ser gravada no projeto:

```powershell
$env:ADMIN_PASSWORD = Read-Host "Senha inicial do administrador"
php scripts\criar_admin.php admin@exemplo.com
Remove-Item Env:ADMIN_PASSWORD
```

Saia e entre novamente para carregar as permissões de administrador.
