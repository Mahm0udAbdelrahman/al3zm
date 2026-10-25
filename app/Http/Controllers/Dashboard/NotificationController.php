<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{

    public function getNotifications()
    {
        $user          = Auth::user();
        $notifications = $user->notifications()->orderBy('created_at', 'desc')->paginate();
        $newCount      = $user->unreadNotifications()->count();

        return view('admin.notifications.index', compact('notifications', 'newCount'));
    }
    public function markAsRead(Request $request)
    {
        $notification = auth()->user()->notifications()->find($request->id);

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    }

    public function ReadAll()
    {

        $notification = Auth::user()->unreadNotifications;
        if ($notification) {
            $notification->markAsRead();
            return back();
        }
    }

    public function destroy($id)
    {
        $user         = Auth::user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();

        return redirect()->route('Admin.notifications')->with('success', 'Deleted Notification');
    }

    public function delete()
    {

        $notification = Auth::user();

        if ($notification) {
            $notification->notifications()->delete();
            return back();
        }
    }
}
