<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class OrderPaid extends Notification
{
    public function __construct(public Order $order)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payment captured',
            'body' => "{$this->order->order_number} · {$this->order->formattedAmount()} USD",
            'order_number' => $this->order->order_number,
            'href' => '/orders',
        ];
    }
}
