import { Controller } from '@hotwired/stimulus';
import { BridgeComponent } from '@hotwired/hotwire-native-bridge';

export default class extends BridgeComponent {
    static component = 'microphone-permission';

    connect() {
        super.connect();
        this.send('connect', {}, () => {});
        this.request();
    }

    request() {
        this.send('request', {}, (response) => {
            this.dispatch('granted', { detail: response });
        });
    }

    onGranted(event) {
        const granted = event.detail?.data?.granted ?? event.detail?.granted;
        if (granted) {
            this.element.textContent = 'Microphone: granted.';
        } else {
            this.element.textContent = 'Microphone: denied — enable in iOS Settings.';
        }
    }
}
