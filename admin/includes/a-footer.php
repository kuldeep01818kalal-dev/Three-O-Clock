    </main>
</div>
<!-- End Main Content -->

<footer class="admin-footer">

    <div class="footer-left">

        <div class="brand">

            <i class="bi bi-cup-hot-fill"></i>

            <span>Three O' Clock Cafe</span>

        </div>

        <p>

            Restaurant Management System

            <span class="dot"></span>

            Admin Dashboard

        </p>

    </div>

    <div class="footer-center">

        <span class="footer-badge success">

            <i class="bi bi-circle-fill"></i>

            System Online

        </span>

        <span class="footer-badge">

            Version 1.0.0

        </span>

    </div>

    <div class="footer-right">

        <span>

            © <?= date("Y"); ?>

            Three O' Clock Cafe

        </span>

        <small>

            Designed & Developed by

            <strong>PW_F11_</strong>

        </small>

    </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

<script>

document.addEventListener("DOMContentLoaded",function(){

    const alerts=document.querySelectorAll(".alert");

    alerts.forEach(function(alert){

        setTimeout(function(){

            alert.classList.add("fade");

            setTimeout(function(){

                alert.remove();

            },500);

        },4000);

    });

});
const menuToggle=document.getElementById("menuToggle");

const sidebar=document.getElementById("sidebar");

const overlay=document.getElementById("sidebarOverlay");

menuToggle.onclick=function(){

sidebar.classList.toggle("show");

overlay.classList.toggle("show");

}

overlay.onclick=function(){

sidebar.classList.remove("show");

overlay.classList.remove("show");

}

function updateClock(){

const now=new Date();

document.getElementById("clock").innerHTML=

now.toLocaleTimeString();

}

setInterval(updateClock,1000);

updateClock();

document.getElementById("fullscreenBtn").onclick=function(){

if(!document.fullscreenElement){

document.documentElement.requestFullscreen();

}else{

document.exitFullscreen();

}

}
</script>

</body>
</html>
