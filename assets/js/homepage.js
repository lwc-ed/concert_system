const slides = Array.from(document.querySelectorAll(".carousel-slide"));
const dots = Array.from(document.querySelectorAll(".carousel-dots button"));
const previousButton = document.querySelector(".carousel-control.prev");
const nextButton = document.querySelector(".carousel-control.next");

let currentSlide = 0;
let carouselTimer = null;

function showSlide(index) {
    currentSlide = (index + slides.length) % slides.length;

    slides.forEach((slide, slideIndex) => {
        slide.classList.toggle("is-active", slideIndex === currentSlide);
    });

    dots.forEach((dot, dotIndex) => {
        dot.classList.toggle("is-active", dotIndex === currentSlide);
    });
}

function startCarousel() {
    carouselTimer = window.setInterval(() => {
        showSlide(currentSlide + 1);
    }, 4200);
}

function restartCarousel() {
    window.clearInterval(carouselTimer);
    startCarousel();
}

if (slides.length > 0) {
    previousButton?.addEventListener("click", () => {
        showSlide(currentSlide - 1);
        restartCarousel();
    });

    nextButton?.addEventListener("click", () => {
        showSlide(currentSlide + 1);
        restartCarousel();
    });

    dots.forEach((dot, index) => {
        dot.addEventListener("click", () => {
            showSlide(index);
            restartCarousel();
        });
    });

    startCarousel();
}
