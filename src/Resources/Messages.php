<?php

namespace FLAIRUK\BookingCom\Resources;

use FLAIRUK\BookingCom\Response;

/**
 * Guest–property messaging for accommodation reservations.
 *
 * Available in API version 3.1, and in 3.2 for partners with Beta access:
 * use BookingCom::version('3.1')->messages() if your default is 3.2.
 *
 * @see https://developers.booking.com/demand/docs/messaging/about-messaging
 */
class Messages extends Resource
{
    /**
     * @param  array<string, mixed>  $message  ['accommodation' => ..., 'conversation' => ..., 'content' => ...]
     */
    public function send(array $message): Response
    {
        return $this->client->post('messages/send', $message, retry: false);
    }

    /**
     * A conversation, by reservation or conversation id.
     *
     * @param  array<string, mixed>  $query  ['accommodation' => ..., 'reservation' => ...] or ['accommodation' => ..., 'conversation' => ...]
     */
    public function conversation(array $query): Response
    {
        return $this->client->post('messages/conversations', $query);
    }

    /**
     * Messages not yet confirmed with confirm().
     */
    public function latest(): Response
    {
        return $this->client->post('messages/latest');
    }

    /**
     * Mark messages as received so latest() stops returning them.
     *
     * @param  list<string>  $messageIds
     */
    public function confirm(array $messageIds): Response
    {
        return $this->client->post('messages/latest/confirm', ['messages' => array_values($messageIds)], retry: false);
    }

    /**
     * @param  array<string, mixed>  $attachment  ['accommodation', 'conversation', 'file_name', 'file_type', 'file_size', 'file_content' (base64)]
     */
    public function uploadAttachment(array $attachment): Response
    {
        return $this->client->post('messages/attachments/upload', $attachment, retry: false);
    }

    /**
     * @param  array<string, mixed>  $query  ['accommodation' => ..., 'conversation' => ..., 'attachment' => ...]
     */
    public function downloadAttachment(array $query): Response
    {
        return $this->client->post('messages/attachments/download', $query);
    }

    /**
     * @param  array<string, mixed>  $query  ['accommodation' => ..., 'conversation' => ..., 'attachment' => ...]
     */
    public function attachmentMetadata(array $query): Response
    {
        return $this->client->post('messages/attachments/metadata', $query);
    }
}
