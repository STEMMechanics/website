@php
    $notifications = [];
    $message = session('message');
    if (is_array($message)) {
        $notifications[] = ['title' => $message['title'] ?? '', 'message' => $message['text'] ?? '', 'type' => $message['type'] ?? 'info'];
    } elseif (is_string($message) && $message !== '') {
        $notifications[] = ['title' => session('message-title', ''), 'message' => $message, 'type' => session('message-type', 'info')];
    }
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        if (is_string(session($type)) && session($type) !== '') {
            $notifications[] = ['title' => '', 'message' => session($type), 'type' => $type];
        }
    }
    foreach (['database_backup_notice', 'file_backup_notice'] as $key) {
        $notice = session($key);
        if (is_array($notice)) {
            $notifications[] = ['title' => '', 'message' => $notice['text'] ?? '', 'type' => $notice['type'] ?? 'info'];
        }
    }
    if (is_string(session('inline_message')) && session('inline_message') !== '') {
        $notifications[] = ['title' => '', 'message' => session('inline_message'), 'type' => session('inline_message_type', 'info')];
    }
    if (request()->routeIs('admin.user.index') && is_string(session('status')) && session('status') !== '') {
        $notifications[] = ['title' => '', 'message' => session('status'), 'type' => 'success'];
    }
    if (request()->routeIs('admin.cost-centre.allocations', 'admin.timesheet.index') && $errors->any()) {
        $notifications[] = ['title' => 'Could not save changes', 'message' => implode("\n", $errors->all()), 'type' => 'error'];
    }
@endphp
@foreach($notifications as $notification)
    @if(is_string($notification['message']) && $notification['message'] !== '')
        <script>
            SM.alert(@js($notification['title']), @js($notification['message']), @js($notification['type']));
        </script>
    @endif
@endforeach
