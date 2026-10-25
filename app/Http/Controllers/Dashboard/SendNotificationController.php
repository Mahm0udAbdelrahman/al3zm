<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\SendNotificationService;
use App\Http\Requests\Dashboard\SendNotification\SendNotificationRequest;


class SendNotificationController extends Controller
{
    public function __construct(public SendNotificationService $service){}
    public function index()
    {
        $data = $this->service->index();
        return view('dashboard.pages.send_notifications.index', compact('data'));
    }

    public function create()
    {
        return view('dashboard.pages.send_notifications.create');
    }

    public function store(SendNotificationRequest $request)
    {

        return $this->service->store($request->validated());
    }

    public function destroy($id)
    {
        $this->service->destroy($id);
        return redirect()
            ->route('Admin.send_notifications.index')
            ->with('success', '✅ Notification deleted successfully!');
    }
}
