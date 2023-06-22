const appId = "sandbox-sq0idb-FYI93DDPJk0wJvaU0ye4MQ";
const locationId = "LQ0C6GMZEWVQ0";
const square = null;

export const initCard = (): Object => {
    const scriptSrc = "https://sandbox.web.squarecdn.com/v1/square.js";
    if (!document.querySelector(`script[src="${scriptSrc}"]`)) {
        const script = document.createElement("script");
        script.type = "text/javascript";
        script.src = scriptSrc;
        script.onload = async () => {
            if (!window.Square) {
                console.log("Square failed to load properly");
            }

            let payments;
            try {
                payments = window.Square.payments(appId, locationId);
            } catch (e) {
                console.log("Square: Missing credentials", e);
                return;
            }

            let card;
            try {
                card = await payments.card();
                await card.attach("#card-container");
            } catch (e) {
                console.error("Initializing Card failed", e);
                return;
            }
        };

        document.head.appendChild(script);
    }
};
