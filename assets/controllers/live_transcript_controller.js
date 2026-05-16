import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content'];
    static values = { url: String };

    connect() {
        if (!this.hasUrlValue) {
            return;
        }

        this.source = new EventSource(this.urlValue);
        this.source.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                if (data.type) {
                    this.appendJsonEvent(data);
                }
            } catch {
                // Turbo stream HTML handled by Turbo
            }
        };
    }

    disconnect() {
        this.source?.close();
    }

    appendJsonEvent(data) {
        const p = document.createElement('p');
        p.className = `event event-${data.type}`;
        const time = document.createElement('time');
        time.textContent = new Date(data.occurredAt).toLocaleTimeString();
        p.appendChild(time);
        const strong = document.createElement('strong');
        strong.textContent = data.type;
        p.appendChild(strong);
        if (data.payload?.text) {
            p.appendChild(document.createTextNode(' ' + data.payload.text));
        }
        this.contentTarget.appendChild(p);
        const muted = this.contentTarget.querySelector('.muted');
        muted?.remove();
    }
}
