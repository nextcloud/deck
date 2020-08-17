---
name: Bug report
about: Create a report to help us improve

---

<!--
Thanks for reporting issues back!

Guidelines for submitting issues:

* Please search the existing issues first, it's likely that your issue was already reported or even fixed.
  
* SECURITY: Report any potential security bug to us via our HackerOne page (https://hackerone.com/nextcloud) following our security policy (https://nextcloud.com/security/) instead of filing an issue in our bug tracker.  

* The issues in other components should be reported in their respective repositories: You will find them in our GitHub Organization (https://github.com/nextcloud/)
  
* You can also use the Issue Template app to prefill most of the required information: https://apps.nextcloud.com/apps/issuetemplate
-->

<!--- Please keep this note for other contributors -->

### How to use GitHub

* Please use the 👍 [reaction](https://blog.github.com/2016-03-10-add-reactions-to-pull-requests-issues-and-comments/) to show that you are affected by the same issue.
* Please don't comment if you have no relevant information to add. It's just extra noise for everyone subscribed to this issue.
* Subscribe to receive notifications on status change and new comments. 


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
