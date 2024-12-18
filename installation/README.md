# Installation Guide

## Prerequisites
- Linux server with root access
- Docker
- Docker Compose
- E-Mail mailbox with SMTP access
- Cleverpush account and Cleverpush API key
- Public IP address and domain name
- DESIVE² app compiled with the domain name and Cleverpush API key

## Installation
1. Clone the repository
1. Create a folder to contain the docker files and application data (e.g. `mkdir /opt/desive2`)
1. Copy the contents of the `docker` folder to the folder created in the previous step
1. Set the permissions on the `acme` folder in `config` and its contents to `600`
1. Enter your domain names in the `docker-compose.yml` file
    1. In case you want more than one instance, don't forget to change the names of the traefik routes (in the labels) to avoid collisions.
1. Enter your E-Mail mailbox credentials in the `msmtp/msmtprc` file
1. Add the contents of the `webapp` folder to the `app` folder in your docker folder
1. Set all settings for the webapp
    1. Create htpasswd auth credentials for administrative people to access the webapp and store them in `app/private/.htpasswd`
    1. Copy the `app/private/key.php.default` file to `app/private/key.php` and enter the needed settings (see further down in Configuration)
1. Set permissions on the `app` folder to `750` and the owner to `www-data:www-data` (`sudo chmod -R 750 app/` and `sudo chown -R www-data:www-data  app/`)
1. Build the docker containers with `docker-compose build`
1. Start the docker containers with `docker-compose up -d`
1. Access phpmyadmin (according to Dockerfile) and import the `/installation/db/desive2.sql` file (alternatively, use the `mysql` command line tool within the db container)
1. The installation is now complete and the webapp should be accessible via the domain name you entered in the `docker-compose.yml` file.
1. Replace links to data privacy statement, imprint and contact form in the source code of the webapp with your own links by searching for `desive2.org` and replacing it with your domain name.

## Entry Points
1. Administrative interface: `https://subdomain.domain.tld/dbMask/index.php`
    1. Login using the credentials you entered in the `.htpasswd` file
1. Participants
    1. Registration: `https://subdomain.domain.tld/register.php`
    1. Password reset: `https://subdomain.domain.tld/resetPassword.php`
1. App
    1. Entry point: `https://subdomain.domain.tld/DBConnect.php` with auth credentials


## Configuration
The `key.php` file in the `app/private` folder contains all the settings for the webapp. The file should look like this:
```php
<?php
define('SERVERNAME', 'db'); // The name of the database server according to the docker-compose.yml file
define('USERNAME', 'desive2'); // The username for the database
define('PASSWORD', 'XXXXXXXXX'); // The password for the database
define('DBNAME', 'desive2'); // The name of the database
define('ENCRYPTION_KEY', '!@#$%^&*'); // The encryption key for the database (used to store sensitive data encrypted (reversible, not using hashing))
define('CLEVERPUSH_API_KEY', 'XXXXXXXXXXXXXXXXXXXX'); // The Cleverpush API key
define('CLEVERPUSH_CHANNEL_ID', 'XXXXXXXXXXXXXXXXXXXX'); // The Cleverpush channel ID (see Cleverpush documentation)
define('CREATION_API_KEY', 'RANDOMSTRING'); // The API key for the creation of new users (used to access the API interface from the registration form, can be used to create other entry points)
define('API_BASE_URL', 'https://subdomain.domain.tld'); // The base URL for the webapp
define('MAIL_FROM_ADDRESS', 'desive2@subdomain.domain.tld'); // The E-Mail address to send E-Mails from
define('MAIL_FROM_NAME', 'DESIVE²'); // The name to send E-Mails from
define('MAIL_TO_ADDRESS_INTERVIEW', 'desive2@subdomain.domain.tld'); // The E-Mail address to send interview requests to
define('MAIL_TO_NAME_INTERVIEW', 'Project Partner'); // The name to send interview requests to
define('WAITLIST_ACTIVE', true); // Whether the waitlist is active or not (users will receive credentials for the app immediately if false)
define('UPLOADS_MAXFILESIZE_IN_BYTES', 104857600); // The maximum file size for uploads in bytes
define('SYSTEM_AUTHORIZATION_USER', 'system');
define('SYSTEM_AUTHORIZATION_PASSWORD', 'vbm7dhr7gaf0bma-ETX');
?>
```

The `msmtprc` file in the `msmtp` folder contains the settings for the SMTP server. It should look like this:
```
# Set default values for all following accounts.
defaults

# Set a default account
# Remove inline comments completely or they will be interpreted as part of the values
account         desive                  # The account name
host            SMTP-HOST               # The SMTP server           # Replace with your SMTP server
port            587                     # Submission port           # Replace with your SMTP port
set_from_header on                      # Set the From header
tls             on                      # Use TLS
tls_starttls    on                      # Use STARTTLS
auth            on                      # Enable SMTP authentication
user            ACCOUT                  # Username                  # Replace with your SMTP username
password	    PASSWORD                # Password                  # Replace with your SMTP password
from            desive2@email.uni-kiel.de   # Email address         # Replace with your email address
logfile         /var/log/msmtp.log      # Log file

account default : desive                # Set the default account
```


