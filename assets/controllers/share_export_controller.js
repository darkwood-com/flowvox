import { BridgeComponent } from '@hotwired/hotwire-native-bridge';

export default class extends BridgeComponent {
    static component = 'share-export';

    share(title, text, url) {
        this.send('share', { title, text, url }, () => {});
    }
}
