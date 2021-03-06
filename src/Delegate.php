<?php

namespace AbuseIO\Hook;

use AbuseIO\Jobs\FindContact;
use AbuseIO\Hook\HookInterface;
use AbuseIO\Models\Incident;
use Zend\Http\Client;
use Log as Logger;

class Delegate implements HookInterface
{
    /**
     * dictated by HookInterface
     * the method called from hook-common
     *
     * @param $object
     * @param $event
     */
    public static function call($object, $event)
    {
        // valid models we listen to
        $models =
            [
                'Event' => \AbuseIO\Models\Event::class,
                'Ticket' => \AbuseIO\Models\Ticket::class,
            ];

        if ($object instanceof $models['Event'] && $event == 'created') {
            // convert the event to an incident
            $incident = Incident::fromEvent($object);
            $contact = FindContact::byIP($incident->ip);

            if (!is_null($contact->api_host) && !is_null($contact->token)) {
                // we have a contact with a delegated AbuseIO instance
                // use that AbuseIO's api to create a incident

                $token = $contact->token;
                $url = $contact->api_host . "/incidents";

                // send incident
                Logger::notice('Sending incident to ' . $url);
                $client = new Client($url);
                $client->setHeaders([
                    'Accept'      => 'application/json',
                    'X-API-TOKEN' => $token
                ]);
                $client->setParameterPost($incident->toArray());
                $client->setMethod('POST');
                $response = $client->send();

                if (!$response->isSuccess()) {
                   Logger::notice(
                       sprintf(
                           "Failure, statuscode: %d\n body: %s\n",
                           $response->getStatusCode(),
                           $response->getBody()
                       )
                   );
                }
            }
        }
    }

    /**
     * is this hook enabled
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return true;
    }
}