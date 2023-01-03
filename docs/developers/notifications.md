# Working with the notifications

You can display notifications to the user by setting a flash message in a controller.
There are two kinds of flashes that you can set: `error` and `success`.
The notification will be displayed at the top of the next screen (e.g. after a redirection).

For instance:

```php
<?php

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\TicketRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TicketsController extends BaseController
{
    #[Route('/tickets/{uid}', name: 'update ticket', methods: ['POST'])]
    public function update(
        Ticket $ticket,
        Request $request,
        TicketRepository $ticketRepository,
        ValidatorInterface $validator,
    ): Response {
        $type = $request->request->get('type', '');
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update ticket', $csrfToken)) {
            $this->addFlash('error', $this->csrfError());
            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        $ticket->setType($type);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            $error = implode(' ', $this->formatErrors($errors));
            $this->addFlash('error', $error);
            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        $ticketRepository->save($ticket, true);

        $this->addFlash('success', new TranslatableMessage('The ticket has been updated'));

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
```
