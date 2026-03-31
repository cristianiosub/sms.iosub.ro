# SMS Platform — Ghid de instalare

Platforma profesionala multi-tenant pentru trimitere SMS prin sendsms.ro
Destinatie: `sms.iosub.ro` -> `/home/i0sub/public_html/sms.iosub.ro`

---

## Structura proiectului

```
sms.iosub.ro/
├── public/              <- Document Root Apache
│   ├── index.php        <- Front controller
│   ├── .htaccess        <- URL curate, HTTPS, securitate
│   └── assets/
│       ├── css/app.css
│       └── js/app.js
├── config/
│   └── config.php       <- Configurare (DB, secret, etc.)
├── src/
│   ├── controllers/     <- Logic pagini
│   └── helpers/         <- DB, Auth, Router, SendSmsApi
├── templates/           <- HTML/PHP templates
├── cron/
│   └── send_scheduled.php
├── install/
│   ├── schema.sql
│   └── setup.php        <- STERGE dupa rulare!
├── logs/
└── tmp/uploads/
```

---

## Instalare pas cu pas

### 1. Incarca fisierele pe server

Structura pe server:
- Document Root: `/home/i0sub/public_html/sms.iosub.ro/public`
- Restul fisierelor in: `/home/i0sub/public_html/sms.iosub.ro/`

Seteaza Document Root in cPanel la `public/`

### 2. Baza de date

Creeaza DB in cPanel, apoi editeaza `config/config.php`:

```php
define('DB_NAME', 'i0sub_sms_platform');
define('DB_USER', 'i0sub_smsuser');
define('DB_PASS', 'PAROLA_PUTERNICA');
```

### 3. SECRET_KEY

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Pune rezultatul in `config.php` la `SECRET_KEY`.

### 4. Ruleaza setup

```bash
php install/setup.php
```

Creeaza schema, superadmin si clientii initiali (CyberShield + Pizzeria Volare).

```bash
# Obligatoriu dupa rulare:
rm install/setup.php
```

### 5. Cron job (SMS-uri programate)

In cPanel -> Cron Jobs:

```
* * * * * /usr/bin/php /home/i0sub/public_html/sms.iosub.ro/cron/send_scheduled.php >> /home/i0sub/public_html/sms.iosub.ro/logs/cron.log 2>&1
```

### 6. Permisiuni

```bash
chmod 750 logs/ tmp/ tmp/uploads/
chmod 640 config/config.php
```

### 7. Credentiale Pizzeria Volare

Logheaza-te -> `/admin/clients` -> editeaza Volare -> adauga SendSMS username + API key.

---

## Functionalitati

| Feature | Descriere |
|---------|-----------|
| Multi-tenant | Fiecare client izolat complet |
| Dashboard | Statistici live, grafice 30 zile, sold API |
| Quick SMS | Trimitere imediata sau programata, tag input telefoane |
| Campanii | Bulk, pauza, progress live, personalizare |
| Liste contacte | Import CSV pana la 15.000 randuri, mapare coloane |
| SMS programate | Cron la fiecare minut, retry automat x3 |
| Delivery Reports | Webhook /dlr, update status in timp real |
| Rapoarte | Per perioada, per campanie, per numar de telefon |
| Personalizare | {prenume} {nume} {telefon} in mesaje |
| Admin | Clienti si utilizatori, roluri (superadmin/admin/user) |
| Securitate | CSRF, brute-force protection, session binding, audit log |
| URL-uri curate | .htaccess fara .php in URL |
| HTTPS enforced | Redirect automat HTTP -> HTTPS |

---

## Format CSV pentru import

```csv
telefon,prenume,nume,restaurant
0712345678,Ion,Popescu,Volare
+40723456789,Maria,Ionescu,Volare
```

Delimiter auto-detectat: virgula, punct-virgula, tab, pipe.

---

## Delivery Report Webhook

URL setat automat: `https://sms.iosub.ro/dlr?msg={MSG_ID}&status=%d`

---

## Credentiale implicite dupa setup

- **CyberShield**: username=cybershield, apikey=cel furnizat
- **Pizzeria Volare**: de completat din /admin/clients
- **Superadmin**: email + parola introduse la setup

---

*SMS Platform v1.0 — sms.iosub.ro*
