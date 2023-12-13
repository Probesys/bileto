# Working with the tickets' events

Several events can occur during the lifetime of a ticket.
These events are dispatched in different parts of the application.
There are multiple subscribers listening to these events in order to centralise the business logic.

The events and the subscribers related to the tickets' events are located under [`src/TicketActivity`](/src/TicketActivity/).

To dispatch an event, just use the default EventDispatcher from Symfony:

```php
use App\TicketActivity\MessageEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MyController
{
    public function someAction(EventDispatcherInterface $eventDispatcher)
    {
        // ...

        // considering that you've created a Message entity somewhere above
        $messageEvent = new MessageEvent($message);
        $eventDispatcher->dispatch($messageEvent, MessageEvent::CREATED);
    }
}
```
