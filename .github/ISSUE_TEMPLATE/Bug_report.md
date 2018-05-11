---
name: Bug report
about: Create a report to help us improve

---

**Describe the bug**
A clear and concise description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

**Expected behavior**
A clear and concise description of what you expected to happen.

**Screenshots**
If applicable, add screenshots to help explain your problem.

**Client details:**
 - OS: [e.g. iOS]
 - Browser [e.g. chrome, safari]
 - Version [e.g. 22]
 - Device: [e.g. iPhone6, desktop]

<details>
<summary>Server details</summary>
<!--
You can use the Issue Template application to prefill most of the required information: https://apps.nextcloud.com/apps/issuetemplate
-->

**Operating system**:

**Web server:**

**Database:**

**PHP version:**

**Nextcloud version:** (see Nextcloud admin page)

**Where did you install Nextcloud from:**

**Signing status:**

```
Login as admin user into your Nextcloud and access
http://example.com/index.php/settings/integrity/failed
paste the results here.
```

**List of activated apps:**

```
If you have access to your command line run e.g.:
sudo -u www-data php occ app:list
from within your Nextcloud installation folder
```

**Nextcloud configuration:**

```
If you have access to your command line run e.g.:
sudo -u www-data php occ config:list system
from within your Nextcloud installation folder

or

Insert your config.php content here
Make sure to remove all sensitive content such as passwords. (e.g. database password, passwordsalt, secret, smtp password, …)
```

**Are you using an external user-backend, if yes which one:** LDAP/ActiveDirectory/Webdav/...

</details>

<details>
<summary>Logs</summary>

#### Nextcloud log (data/nextcloud.log)
```
Insert your Nextcloud log here
```

#### Browser log
```
Insert your browser log here, this could for example include:

a) The javascript console log
b) The network log
c) ...
```

</details>
