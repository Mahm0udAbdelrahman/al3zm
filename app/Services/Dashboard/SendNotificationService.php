<?php
namespace App\Services\Dashboard;

use App\Models\SendNotification;
use Kreait\Firebase\Factory;

class SendNotificationService
{
    public function __construct(public SendNotification $model)
    {}

    public function index()
    {
        return $this->model->latest()->paginate(10);
    }

    public function store($data)
    {
        $notification = $this->model->create($data);

        $factory = (new Factory)
            ->withServiceAccount(storage_path(env('FIREBASE_CREDENTIALS')));

        $messaging = $factory->createMessaging();

        $message = [
            'topic'        => $data['topic'],
            'notification' => [
                'title' => $data['title'],
                'body'  => $data['body'],
            ],
            'data'         => [
                'title' => $data['title'],
                'body'  => $data['body'],
            ],
        ];

        try {
            $messaging->send($message);

            return redirect()
                ->route('Admin.send_notifications.index')
                ->with('success', 'Notification sent successfully via Firebase!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send notification: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $notification = $this->model->findOrFail($id);

        $notification->delete();

        return $notification;
    }

}
