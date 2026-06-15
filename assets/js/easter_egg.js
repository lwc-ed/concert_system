(() => {
    let logoClicks = 0;
    let resetTimer = null;
    let isRunning = false;

    function spawnCakeRain() {
        if (isRunning) {
            return;
        }

        isRunning = true;
        const dinosaur = document.createElement("span");
        dinosaur.className = "dino-runner";
        dinosaur.textContent = "🦖";
        document.body.appendChild(dinosaur);
        dinosaur.addEventListener("animationend", () => dinosaur.remove(), {once: true});

        const cakeCount = 55;
        const icons = ["🍰", "🍓", "🍰", "🍓"];

        for (let index = 0; index < cakeCount; index += 1) {
            const cake = document.createElement("span");
            cake.className = "cake-rain";
            cake.textContent = icons[Math.floor(Math.random() * icons.length)];
            cake.style.left = `${Math.random() * 100}vw`;
            cake.style.fontSize = `${24 + Math.random() * 26}px`;
            cake.style.animationDuration = `${2.2 + Math.random() * 1.9}s`;
            cake.style.animationDelay = `${Math.random() * 0.7}s`;
            cake.style.setProperty("--cake-drift", `${Math.random() * 120 - 60}px`);
            cake.style.setProperty("--cake-spin", `${Math.random() > 0.5 ? 720 : -720}deg`);

            document.body.appendChild(cake);
            cake.addEventListener("animationend", () => cake.remove(), {once: true});
        }

        window.setTimeout(() => {
            isRunning = false;
        }, 4700);
    }

    function handleLogoClick(event) {
        event.preventDefault();
        event.stopPropagation();
        logoClicks += 1;
        window.clearTimeout(resetTimer);

        resetTimer = window.setTimeout(() => {
            logoClicks = 0;
        }, 5000);

        if (logoClicks >= 5) {
            logoClicks = 0;
            spawnCakeRain();
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        const logo = document.querySelector(".brand-mark");

        if (!logo) {
            return;
        }

        logo.style.cursor = "pointer";
        logo.title = "Click five times";
        logo.addEventListener("click", handleLogoClick);
    });
})();
