<?php
namespace App\Services\Api;

use Illuminate\Support\Facades\Auth;

class NotificationService
{

    public function index($limit = 10)
    {
        $vendor        = Auth::user();
        $notifications = $vendor->notifications()->latest()->paginate($limit);
        $lang          = request()->header('Accept-Language', 'ar');

        $notifications->getCollection()->transform(function ($note) use ($lang) {
            $data = $note->data;

            $titleKey = 'title_' . $lang;
            $bodyKey  = 'body_' . $lang;

            $data['title'] = $data[$titleKey] ?? '';
            $data['body']  = $data[$bodyKey] ?? '';

            unset($data['title_ar'], $data['body_ar'], $data['title_en'], $data['body_en']);

            $note->data = $data;
            return $note;
        });

        return $notifications;
    }

}
