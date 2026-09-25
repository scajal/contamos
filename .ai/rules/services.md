---
paths:
  - 'app/Modules/**/Services/**'
---

# Services

## Service folder is a scheduled process
A Service is a folder named after a background process holding Actions/ (the work, in the usual Action grammar, reusing the module's Actions and Reports) and Commands/ (the Artisan command that runs it on a schedule). Add Contracts/ and Providers/ only for pluggable providers. Register its Commands directory in bootstrap/app.php withCommands and its schedule in the module Routes/console.php.
