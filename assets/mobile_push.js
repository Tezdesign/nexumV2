import { PushNotifications } from '@capacitor/push-notifications';

const registerPush = async () => {
    try {
        let permStatus = await PushNotifications.checkPermissions();
        if (permStatus.receive === 'prompt') {
            permStatus = await PushNotifications.requestPermissions();
        }
        if (permStatus.receive !== 'granted') {
            return;
        }
        await PushNotifications.register();
    } catch (e) {
        console.error('Push error:', e);
    }
};

// only run if inside native capacitor app
if (window.Capacitor && window.Capacitor.isNativePlatform()) {
    registerPush();

    PushNotifications.addListener('registration', (token) => {
        fetch('/mobile/api/save-fcm-token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token.value })
        }).catch(err => console.error('Token save error:', err));
    });
}
