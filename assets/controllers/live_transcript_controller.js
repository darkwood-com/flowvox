import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content'];
    static values = { url: String };

    connect() {
        this.partialBlocks = [];
        this.liveLine = null;

        if (!this.hasUrlValue) {
            return;
        }

        // Send mercureAuthorization cookie (set by Twig mercure() with subscribe option)
        this.source = new EventSource(this.urlValue, { withCredentials: true });
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
        if (data.type === 'transcription_partial' && data.payload?.text) {
            this.appendPartialBlock(data.payload.text, data.occurredAt);
            return;
        }

        if (data.type === 'transcription_final' && data.payload?.text) {
            this.appendPartialBlock(data.payload.text, data.occurredAt, true);
            return;
        }

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
        this.contentTarget.querySelector('.muted')?.remove();
    }

    appendPartialBlock(text, occurredAt, isFinal = false) {
        const trimmed = String(text).trim();
        if (!trimmed) {
            return;
        }

        if (!isFinal) {
            this.partialBlocks.push(trimmed);
        }

        const cumulative = this.partialBlocks.join(' ');

        if (!this.liveLine) {
            this.liveLine = document.createElement('div');
            this.liveLine.className = 'live-partial';
            this.contentTarget.appendChild(this.liveLine);
            this.contentTarget.querySelector('.muted')?.remove();
        }

        const time = new Date(occurredAt).toLocaleTimeString();
        this.liveLine.innerHTML = `
            <p class="live-segment"><time>${time}</time> <span class="segment-latest">${this.escapeHtml(trimmed)}</span></p>
            <p class="live-cumulative muted">${this.escapeHtml(cumulative)}</p>
        `;

        if (isFinal) {
            this.partialBlocks = [];
            this.liveLine = null;
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
