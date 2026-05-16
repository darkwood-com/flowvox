import { BridgeComponent } from '@hotwired/hotwire-native-bridge';

export default class extends BridgeComponent {
    static component = 'native-recorder';

    start(sessionId) {
        this.send('start', { sessionId }, () => {});
    }

    stop() {
        this.send('stop', {}, () => {});
    }
}
