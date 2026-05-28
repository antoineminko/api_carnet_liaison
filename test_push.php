<?php
try {
    \ = app('firebase.messaging');
    \ = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', 'dXM4l2SWRza9uuD0wNNuuN:APA91bHJm5I-iMQcrfilBQ1RbPnMaDsLxpeCXpEHEI2ZikN-NkLBqBC0TwEzphTuv0K4tpXrxtivTMnb-rS82kiNHeM_1-LR6kZz8HgPKhIwfGLlgSn-vUE')
        ->withNotification(\Kreait\Firebase\Messaging\Notification::create('Test Local', 'Ceci est un test depuis le PC'));
    \->send(\);
    echo 'TEST_SUCCESS_LOCAL';
} catch (\Exception \) {
    echo 'TEST_FAILED: ' . \->getMessage();
}

