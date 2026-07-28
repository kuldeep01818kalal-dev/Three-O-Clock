/*
==========================================================
Three O' Clock Cafe
Main JavaScript
==========================================================
*/

document.addEventListener("DOMContentLoaded", () => {

    initializeNavbar();

    initializeSmoothScroll();

    initializeBackToTop();

    initializeAnimations();

});

/*==========================================================
Navbar
==========================================================*/

function initializeNavbar(){

    const navbar = document.querySelector(".navbar");

    if(!navbar) return;

    window.addEventListener("scroll", () => {

        if(window.scrollY > 60){

            navbar.classList.add("shadow");

        }else{

            navbar.classList.remove("shadow");

        }

    });

}

/*==========================================================
Smooth Scroll
==========================================================*/

function initializeSmoothScroll(){

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {

        anchor.addEventListener("click", function(e){

            const target = document.querySelector(this.getAttribute("href"));

            if(target){

                e.preventDefault();

                target.scrollIntoView({

                    behavior:"smooth"

                });

            }

        });

    });

}

/*==========================================================
Back To Top
==========================================================*/

function initializeBackToTop(){

    let button = document.getElementById("backToTop");

    if(!button){

        button = document.createElement("button");

        button.id = "backToTop";

        button.innerHTML = '<i class="bi bi-arrow-up"></i>';

        document.body.appendChild(button);

    }

    window.addEventListener("scroll", () => {

        if(window.scrollY > 300){

            button.classList.add("show");

        }else{

            button.classList.remove("show");

        }

    });

    button.addEventListener("click", () => {

        window.scrollTo({

            top:0,

            behavior:"smooth"

        });

    });

}

/*==========================================================
Simple Fade Animation
==========================================================*/

function initializeAnimations(){

    const elements = document.querySelectorAll(".card,.category-card,.hero-image,.about-image");

    const observer = new IntersectionObserver(entries => {

        entries.forEach(entry => {

            if(entry.isIntersecting){

                entry.target.classList.add("fade-in");

            }

        });

    },{

        threshold:0.15

    });

    elements.forEach(element=>{

        observer.observe(element);

    });

}