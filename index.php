<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <title>ZNS Dental Clinic</title>

  <style>
    :root{
      --blue:#2f63e0;
      --blue-2:#5aa8ff;
      --gray:#5e5e5e;
      --text:#1f1f1f;

      --shadow:0 12px 30px rgba(26,45,90,0.12);

      --soft-grad: linear-gradient(180deg,#ffffff 0%, #f2f6ff 100%);
      --soft-grad-2: linear-gradient(180deg,#f7fbff 0%, #eef4ff 100%);
      --cta-grad: linear-gradient(135deg,#2f63e0,#4e8bff);
      --yellow-grad: linear-gradient(180deg,#ffe584 0%, #ffd54f 100%);
    }

    *{
      box-sizing:border-box;
      margin:0;
      padding:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }

    body{
      color:var(--text);
      background: linear-gradient(180deg, #eef5ff 0%, #ffffff 45%, #eaf1ff 100%);
      font-size:18px;
    }

    .container{
      max-width:1180px;
      margin:0 auto;
      padding:0 22px;
    }

    header{
      background:var(--soft-grad-2);
      padding:18px 0;
      position:sticky;
      top:0;
      z-index:10;
      box-shadow:0 6px 20px rgba(0,0,0,0.04);
    }

    .nav{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:20px;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:700;
    }

    .brand img{
      width:46px;
      height:46px;
      border-radius:50%;
      object-fit:cover;
      background:#fff;
    }

    nav ul{
      display:flex;
      gap:26px;
      list-style:none;
      font-size:16px;
      font-weight:600;
    }

    nav a{
      text-decoration:none;
      color:#2b2b2b;
      transition:color .2s ease;
    }

    nav a:hover{ color:var(--blue); }

    .nav-actions{
      display:flex;
      gap:12px;
      align-items:center;
    }

    .btn{
      border:none;
      padding:13px 22px;
      border-radius:12px;
      cursor:pointer;
      font-weight:700;
      font-size:15px;
      transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .btn.primary{
      background:linear-gradient(135deg,var(--blue),var(--blue-2));
      color:#fff;
      box-shadow:0 8px 18px rgba(47,99,224,.2);
    }

    .btn.light{
      background:#fff;
      color:var(--blue);
      border:1px solid #d7e3ff;
    }

    .btn:hover{
      transform:translateY(-2px);
      box-shadow:0 10px 22px rgba(47,99,224,.25);
    }

    .btn:active{
      transform:translateY(0);
      box-shadow:0 6px 14px rgba(47,99,224,.18);
    }

    .btn:focus{
      outline:2px solid rgba(47,99,224,.35);
      outline-offset:3px;
    }

    .hero{
      padding:70px 0 50px;
      background:linear-gradient(180deg,#eef5ff 0%,#f9fcff 100%);
      position:relative;
      overflow:hidden;
    }

    .hero-grid{
      display:grid;
      grid-template-columns:1.1fr 0.9fr;
      gap:50px;
      align-items:center;
    }

    .hero h1{
      font-size:56px;
      line-height:1.1;
      margin-bottom:18px;
    }
    .hero h1 span{ color:var(--blue); }

    .hero p{
      color:var(--gray);
      font-size:20px;
      line-height:1.8;
      margin-bottom:22px;
      max-width:62ch;
    }

    .hero .img-collage{
      display:grid;
      grid-template-columns:repeat(2,1fr);
      gap:12px;
    }

    .hero .img-collage img{
      width:100%;
      height:200px;
      object-fit:cover;
      border-radius:18px;
      box-shadow:var(--shadow);
      background:#fff;
    }

    .shape-dot,
    .shape-blob{
      position:absolute;
      border-radius:50%;
      background:#4ab6ff;
      opacity:.95;
      animation: floaty 4s ease-in-out infinite;
    }

    .shape-dot{ width:26px; height:26px; }

    .shape-blob{
      width:110px;
      height:40px;
      border-radius:24px;
      background:#3b6ee9;
      animation: floaty 5s ease-in-out infinite;
    }

    @keyframes floaty{
      0%,100%{transform:translateY(0)}
      50%{transform:translateY(-12px)}
    }

    .about-strip{
      padding:36px 0;
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:40px;
      align-items:center;
    }

    .about-card{
      background:var(--soft-grad);
      border-radius:18px;
      box-shadow:var(--shadow);
      padding:18px;
      display:flex;
      align-items:center;
      gap:16px;
    }

    .about-card img{
      width:190px;
      height:190px;
      border-radius:16px;
      object-fit:cover;
      background:#fff;
    }

    .about-text h4{
      color:var(--blue);
      font-size:14px;
      letter-spacing:1px;
      font-weight:800;
    }

    .about-text h2{
      font-size:32px;
      margin:8px 0;
    }

    .about-text p{
      color:var(--gray);
      font-size:17px;
      line-height:1.8;
    }

    .section{ padding:58px 0; }

    .section-title{
      text-align:center;
      margin-bottom:30px;
    }

    .section-title h3{
      color:var(--blue);
      font-size:14px;
      letter-spacing:1px;
      margin-bottom:6px;
      font-weight:800;
    }

    .section-title h2{ font-size:32px; }

    .services{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:20px;
    }

    .service-card{
      background:var(--soft-grad);
      padding:24px;
      border-radius:18px;
      box-shadow:var(--shadow);
      min-height:330px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      position:relative;
      overflow:hidden;
      transition:.35s;
    }

    .service-card img{
      width:100%;
      height:180px;
      border-radius:14px;
      object-fit:cover;
      margin-bottom:12px;
      background:#fff;
    }

    .service-card h4{ font-size:19px; margin-bottom:8px; }

    .service-card p{
      font-size:15px;
      color:var(--gray);
      line-height:1.7;
      margin-bottom:10px;
    }

    .service-card a{
      font-size:14px;
      color:var(--blue);
      text-decoration:none;
      font-weight:700;
    }

    .cta{
      background:var(--cta-grad);
      color:#fff;
      border-radius:20px;
      padding:28px;
      display:grid;
      grid-template-columns:1.2fr .8fr;
      gap:20px;
      align-items:center;
      box-shadow:var(--shadow);
    }

    .cta h4{
      font-size:14px;
      letter-spacing:1px;
      opacity:.9;
      font-weight:800;
    }

    .cta h2{ font-size:30px; margin:8px 0; }

    .cta p{
      font-size:17px;
      opacity:.95;
      line-height:1.8;
      margin-bottom:10px;
    }

    .cta img{
      width:100%;
      border-radius:16px;
      height:230px;
      object-fit:cover;
      background:#fff;
    }

    .doctors{
      display:grid;
      grid-template-columns:repeat(5,1fr);
      gap:14px;
    }

    .doc-card{
      background:var(--soft-grad);
      border-radius:14px;
      padding:12px;
      box-shadow:var(--shadow);
      position:relative;
      overflow:hidden;
      transition:.35s;
    }

    .doc-card img{
      width:100%;
      height:170px;
      object-fit:cover;
      border-radius:10px;
      background:#fff;
    }

    .doc-card h5{ font-size:16px; margin:8px 0 3px; }
    .doc-card span{ font-size:14px; color:var(--gray); }

    .testi-grid{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:20px;
      margin-top:18px;
    }

    .testi-card{
      background:var(--yellow-grad);
      border-radius:14px;
      padding:20px;
      box-shadow:var(--shadow);
      min-height:180px;
      position:relative;
      overflow:hidden;
      transition:.35s;
    }

    .testi-card p{ font-size:16px; line-height:1.7; }

    .form-cta{
      background:var(--cta-grad);
      color:#fff;
      border-radius:20px;
      padding:24px;
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:20px;
      align-items:center;
      box-shadow:var(--shadow);
    }

    .form-cta img{
      width:100%;
      border-radius:16px;
      height:210px;
      object-fit:cover;
      background:#fff;
    }

    .form-cta input{
      width:100%;
      margin:8px 0;
      padding:12px 14px;
      border-radius:8px;
      border:none;
      font-size:15px;
    }

    .form-cta button{ margin-top:8px; width:100%; }

    .map-img{
      height:320px;
      border-radius:16px;
      overflow:hidden;
      box-shadow:var(--shadow);
      background:#fff;
    }

    .map-img img{
      width:100%;
      height:100%;
      object-fit:cover;
      display:block;
    }

    .contact-box{
      margin-top:14px;
      background:var(--soft-grad);
      padding:18px;
      border-radius:14px;
      box-shadow:var(--shadow);
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:10px;
      font-size:15px;
      color:var(--gray);
    }

    footer{
      padding:20px 0;
      background:var(--soft-grad-2);
      margin-top:30px;
    }

    footer .container{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:14px;
      flex-wrap:wrap;
      font-size:14px;
    }

    .service-card:hover,
    .doc-card:hover,
    .testi-card:hover{
      transform:translateY(-6px) scale(1.01);
      box-shadow:0 14px 30px rgba(30,65,140,0.16);
    }

    /* Scroll reveal */
    .reveal{
      opacity:0;
      transform:translateY(30px);
      transition:all .8s ease;
    }
    .reveal.show{
      opacity:1;
      transform:translateY(0);
    }

    @media(max-width:900px){
      .hero-grid,
      .about-strip,
      .cta,
      .form-cta{
        grid-template-columns:1fr;
      }
      .services{ grid-template-columns:repeat(2,1fr); }
      .doctors{ grid-template-columns:repeat(3,1fr); }
    }

    @media(max-width:600px){
      nav ul{display:none}
      .services,
      .doctors,
      .testi-grid{
        grid-template-columns:1fr;
      }
      .hero h1{font-size:42px}
      .contact-box{grid-template-columns:1fr}
    }
    #doctors .doctors { 
      display: grid !important; 
      grid-template-columns: repeat(auto-fit, minmax(260px, 280px)); 
      justify-content: center; 
      gap: 28px; 
      max-width: 1100px; 
      margin: 0 auto; 
      padding: 0 12px; 
      box-sizing: border-box; 
      align-items: start; 
      width: 100%; 
    }

    @media (max-width: 980px) { 
      #doctors .doctors { 
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
        gap: 20px; 
      } 
    }

    @media (max-width: 520px) { 
      #doctors .doctors { 
        grid-template-columns: 1fr; 
        gap: 16px; 
        padding: 0 8px; 
      } 
    }
  </style>
</head>
<body>

<!-- HEADER -->
<header>
  <div class="container nav">
    <div class="brand">
      <!-- Put your local logo here -->
      <img src="assets/img/logo.png" alt="logo"/>
      <div>
        <div style="font-size:15px;color:#2a2a2a">ZNS</div>
        <div style="font-size:12px;color:#8a8a8a">Dental Clinic</div>
      </div>
    </div>

    <nav>
      <ul>
        <li><a href="#home">Home</a></li>
        <li><a href="#about">About Us</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#doctors">Doctors</a></li>
        <li><a href="#testimonials">Testimonials</a></li>
        <li><a href="#contacts">Contact</a></li>
      </ul>
    </nav>

    <div class="nav-actions">
      <!-- Update these links if you later connect to PHP pages -->
      <a class="btn light" href="login.php">Login</a>
      <a class="btn primary" href="signup.php">Sign Up</a>
    </div>
  </div>
</header>

<!-- HERO -->
<section id="home" class="hero reveal">
  <span class="shape-dot" style="top:140px; left:58%"></span>
  <span class="shape-blob" style="top:185px; left:49%"></span>

  <div class="container hero-grid">
    <div>
      <h1>Elevating Standard<br/>One Step <span>At a Time</span></h1>
      <p>
        We offer a full range of services and an integrated approach to solving any problems, and this is a guarantee of healthy teeth and oral cavity for all family members.
      </p>
      <a class="btn primary" href="#contacts">Make Appointment</a>
    </div>

    <div class="img-collage">
      <!-- Replace with your local images -->
      <img src="assets/img/service_2.jpg" alt="">
      <img src="assets/img/service_1.jpg" alt="">
      <img src="assets/img/facility_1.jpg" alt="">
      <img src="assets/img/faci_4.jpg" alt="">
    </div>
  </div>
</section>

<!-- ABOUT -->
<section id="about" class="container about-strip reveal">
  <div class="about-card">
    <img src="assets/img/logo.png" alt="">
    <div>
      <div style="color:#4b9bff;font-size:15px;font-weight:700">ZNS Dental Clinic</div>
      <div style="font-size:13px;color:#777">181 Mc Arthur Highway Dalandanan, Valenzuela City</div>
      <div style="font-size:13px;color:#777">Tel: 0932 162 7663</div>
    </div>
  </div>

  <div class="about-text">
    <h4>ABOUT US</h4>
    <h2>Patient health is the highest value in our work</h2>
    <p>
      The latest equipment, high‑precision digital technologies and the best achievements of modern world medicine have allowed us to create a completely new, unprecedented level of painlessness, safety and comfort for patients.
    <br><br>
    </p>
    <a class="btn primary" href="#services">Learn More</a>
  </div>
</section>

<!-- SERVICES -->
<section id="services" class="section reveal" style="background:linear-gradient(180deg,#f8fbff 0%, #eef4ff 100%)">
  <div class="container">
    <div class="section-title">
      <h3>Services</h3>
      <h2>High quality services for you</h2>
    </div>

    <div class="services">
      <div class="service-card">
        <img src="assets/img/implant_dentistry.png" alt="">
        <h4>Implant Dentistry</h4>
        <p>Restore your smile with precision implants, durable materials, and advanced planning for safe placement and long‑term comfort.</p>
        <a href="#contacts">Read more</a>
      </div>
      <div class="service-card">
        <img src="assets/img/surgery_dentistry.jpg" alt="">
        <h4>Surgery Dentistry</h4>
        <p>Gentle surgical care with modern anesthesia, clear recovery guidance, and full attention to your safety and comfort.</p>
        <a href="#contacts">Read more</a>
      </div>
      <div class="service-card">
        <img src="assets/img/dental_treatment.jpg" alt="">
        <h4>Dental Treatment</h4>
        <p>Comprehensive treatment for cavities, gum health, and tooth preservation using modern tools and pain‑free techniques.</p>
        <a href="#contacts">Read more</a>
      </div>
      <div class="service-card">
        <img src="assets/img/cosmetic_dentistry.jpg" alt="">
        <h4>Cosmetic Dentistry</h4>
        <p>Brighten and reshape your smile with veneers, whitening, and cosmetic bonding tailored to your facial harmony.</p>
        <a href="#contacts">Read more</a>
      </div>
      <div class="service-card">
        <img src="assets/img/orthodontics.jpg" alt="">
        <h4>Orthodontics</h4>
        <p>Align teeth with braces or clear aligners, guided by digital scans for accuracy and comfort.</p>
        <a href="#contacts">Read more</a>
      </div>
      <div class="service-card">
        <img src="assets/img/preventive_care.jpg" alt="">
        <h4>Preventive Care</h4>
        <p>Regular cleanings, checkups, and patient education to maintain strong teeth and healthy gums.</p>
        <a href="#contacts">Read more</a>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section container reveal">
  <div class="cta">
    <div>
      <h4>ABOUT US</h4>
      <h2>Make an Appointment</h2>
      <p>
        The latest equipment, high‑precision digital technologies and the best achievements of modern world medicine have allowed us to create a completely new, unprecedented level of painlessness, safety and comfort for patients.
      </p>
      <br>
      <a class="btn light" href="#contacts">Make Appointment</a>
    </div>
    <img src="assets/img/clinic_front.jpg" alt="">
  </div>
</section>

<!-- DOCTORS -->
<section id="doctors" class="section container reveal">
  <div class="section-title">
    <h3>DOCTORS</h3>
    <h2>Meet the Crew</h2>
  </div>

  <div class="doctors">
    <div class="doc-card"><img src="assets/img/ms_joy.jpg" alt=""><h5>Dr. Joy</h5><span>Dentist</span></div>
    <div class="doc-card"><img src="assets/img/dr_salamante.jpg" alt=""><h5>Dr. Paula Glenn Z. Salamante</h5><span>General Dentist</span></div>
    <div class="doc-card"><img src="assets/img/ms_adele.jpg" alt=""><h5>Dr. Adele</h5><span>Dentist</span></div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section id="testimonials" class="section container reveal">
  <div class="section-title">
    <h3>TESTIMONIALS</h3>
    <h2>Our Happy Clients</h2>
    <p style="font-size:16px;color:#777;max-width:70ch;margin:10px auto 0;">
      We use only the best quality materials on the market in order to provide the best products to our patients.
    </p>
  </div>

  <div class="testi-grid">
    <div class="testi-card">
      <p>The best dental clinic in Valenzuela City with a very accommodating staffs. Dra. Glenn is a proficient and informative dentist that can treat your dental problems gently and without anything to worry about. Highly recommended!!!</p>
    </div>
    <div class="testi-card">
      <p>Clinic is well-sanitized and disinfected all throughout. Doc Paula is the best dentist I’ve gone to. She has very gentle but sturdy hands during procedures, and is very caring and informative too. Highly recommended!</p>
    </div>
  </div>
</section>

<!-- FORM CTA -->
<section class="section container reveal">
  <div class="form-cta">
    <img src="assets/img/banner.jpg" alt="">
    <div>
      <h2>Are you still not sure?</h2>
      <p style="font-size:16px;opacity:.95;line-height:1.6">
        Our administrator will select a convenient schedule of visits and answer all questions.
      </p>

      <input type="text" placeholder="Enter your name">
      <input type="text" placeholder="Enter your phone">
      <button class="btn light">Make Appointment</button>
    </div>
  </div>
</section>

<!-- CONTACTS / MAP -->
<!-- CONTACTS / MAP --> <section id="contacts" class="section container reveal"> <?php require_once __DIR__ . "/partials/clinic_map_widget.php"; ?>

  <div class="contact-box">
    <div>📍 181 Mc Arthur Highway Dalandanan, Valenzuela City</div>
    <div>📞 0932 162 7663</div>
    <div>🕒 Mon-Fri: 08:00-20:00 • Sat: 09:00-18:00</div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="brand">
      <img src="assets/img/logo.png" alt="logo"/>
      <div>
        <div style="font-size:15px;color:#2a2a2a">ZNS</div>
        <div style="font-size:12px;color:#8a8a8a">Dental Clinic</div>
      </div>
    </div>

    <div style="font-size:14px;color:#6f6f6f">© 2026 ZNS Dental Clinic</div>

    <a class="btn primary" href="#contacts">Make Appointment</a>
  </div>
</footer>

<script>
  // Scroll reveal (works offline)
  const reveals = document.querySelectorAll(".reveal");
  const onScroll = () => {
    reveals.forEach(el=>{
      const top = el.getBoundingClientRect().top;
      if(top < window.innerHeight - 100){ el.classList.add("show"); }
    });
  };
  window.addEventListener("scroll", onScroll);
  onScroll();
</script>

</body>
</html>