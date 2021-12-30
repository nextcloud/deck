## Implement import

* Create a new importer class extending `ABoardImportService`
* Create a listener for event `BoardImportGetAllowedEvent` to enable your importer.
  > You can read more about listeners on [Nextcloud](https://docs.nextcloud.com/server/latest/developer_manual/basics/events.html?highlight=event#writing-a-listener) doc.

  Example:

```php
class YourCustomImporterListener {
    public function handle(Event $event): void {
        if (!($event instanceof BoardImportGetAllowedEvent)) {
            return;
        }

        $event->getService()->addAllowedImportSystem([
            'name' => YourCustomImporterService::$name,
            'class' => YourCustomImporterService::class,
            'internalName' => 'YourCustomImporter'
        ]);
    }
}
```
  * Register your listener on your `Application` class like this:
```php
$dispatcher = $this->getContainer()->query(IEventDispatcher::class);
$dispatcher->registerEventListener(
    BoardImportGetAllowedEvent::class,
    YourCustomImporterListener::class
);
```
* Use the `lib/Service/Importer/Systems/TrelloJsonService.php` class as inspiration