### Steps to reproduce

1.
2. 
3.

### Expected behaviour
Tell us what should happen

### Actual behaviour
Tell us what happens instead

### Server configuration
**Operating system**:

**Web server:**

**Database:**

**PHP version:**

**Server version:** (see your admin page)

**Deck version:** (see the apps page)

**Updated from an older installed version or fresh install:**

**Signing status:**

```
Login as admin user into your cloud and access 
http://example.com/index.php/settings/integrity/failed 
paste the results here.
```

**List of activated apps:**

```
If you have access to your command line run e.g.:
sudo -u www-data php occ app:list
from within your instance's installation folder
```

**The content of config/config.php:**

```
If you have access to your command line run e.g.:
sudo -u www-data php occ config:list system
from within your instance's installation folder

or 

Insert your config.php content here
(Without the database password, passwordsalt and secret)
```

**Are you using external storage, if yes which one:** local/smb/sftp/...

**Are you using encryption:** yes/no

**Are you using an external user-backend, if yes which one:** LDAP/ActiveDirectory/Webdav/...

#### LDAP configuration (delete this part if not used)

```
With access to your command line run e.g.:
sudo -u www-data php occ ldap:show-config
from within your instance's installation folder

Without access to your command line download the data/owncloud.db to your local
computer or access your SQL server remotely and run the select query:
SELECT * FROM `oc_appconfig` WHERE `appid` = 'user_ldap';


Eventually replace sensitive data as the name/IP-address of your LDAP server or groups.
```

### Client configuration
**Browser:**

**Operating system:**

### Logs
#### Web server error log
```
Insert your webserver log here
```

#### Log file (data/nextcloud.log)
```
Insert your nextcloud.log file here
```

#### Browser log
```
Insert your browser log here, this could for example include:

a) The javascript console log
b) The network log 
c) ...
```
