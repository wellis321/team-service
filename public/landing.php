<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = 'Team Service — Structure Your Organisation';
include INCLUDES_PATH . '/public_header.php';
?>

<style>
/* ── Hero ───────────────────────────────────────────────────────────────────── */
.hero { background: white; padding: 5rem 0; }
.hero-content {
    max-width: 1200px; margin: 0 auto; padding: 0 20px;
    display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center;
}
.hero h1 { font-size: 3rem; font-weight: 700; color: #1f2937; line-height: 1.2; margin-bottom: 1.25rem; }
.hero h1 span { color: #2563eb; }
.hero-subtitle { font-size: 1.2rem; color: #4b5563; line-height: 1.7; margin-bottom: 1.75rem; }
.hero-features { list-style: none; margin-bottom: 2rem; display: flex; flex-direction: column; gap: 0.875rem; }
.hero-features li { display: flex; align-items: flex-start; gap: 0.75rem; color: #374151; font-size: 1rem; }
.hero-features li i { color: #2563eb; font-size: 1.1rem; margin-top: 0.2rem; flex-shrink: 0; }
.hero-features li:nth-child(even) i { color: #10b981; }
.hero-cta { display: flex; gap: 1rem; flex-wrap: wrap; }
.btn-hero {
    padding: 0.875rem 2rem; font-size: 1.05rem; font-weight: 600;
    border-radius: 0.5rem; text-decoration: none; display: inline-flex;
    align-items: center; gap: 0.5rem; transition: all 0.3s;
}
.btn-hero-primary { background: #2563eb; color: white; }
.btn-hero-primary:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,99,235,0.4); }
.btn-hero-outline { background: transparent; color: #2563eb; border: 2px solid #2563eb; }
.btn-hero-outline:hover { background: #eff6ff; transform: translateY(-2px); }
.hero-image img { width: 100%; height: 520px; object-fit: cover; border-radius: 1rem; box-shadow: 0 8px 32px rgba(0,0,0,0.12); }

/* ── Intro banner ────────────────────────────────────────────────────────────── */
.intro-banner {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    color: white; padding: 3rem 0; text-align: center;
}
.intro-banner h2 { font-size: 2rem; margin-bottom: 0.75rem; }
.intro-banner p { font-size: 1.1rem; opacity: 0.9; max-width: 640px; margin: 0 auto; }

/* ── Feature slider ──────────────────────────────────────────────────────────── */
.slider-wrapper { max-width: 1200px; margin: 4rem auto; padding: 0 20px; }
.slider-wrapper h2 { font-size: 2rem; font-weight: 700; color: #1f2937; margin-bottom: 2rem; text-align: center; }
.slider { position: relative; height: 560px; border-radius: 1rem; overflow: hidden; }
.slides { display: flex; width: 300%; height: 100%; transition: transform 0.5s ease-in-out; }
.slide {
    width: 33.333%; height: 100%; position: relative; flex-shrink: 0;
    background-size: cover; background-position: center;
}
.slide::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(30,64,175,0.55) 0%, rgba(37,99,235,0.4) 100%); z-index: 1;
}
.slide-content {
    position: relative; z-index: 2; height: 100%; display: flex; flex-direction: column;
    justify-content: center; padding: 3rem 3rem 3rem 5rem; color: white; max-width: 680px;
    background: rgba(0,0,0,0.25); border-radius: 1rem; backdrop-filter: blur(3px); margin-left: 24px;
}
.slide-content h3 { font-size: 2.25rem; font-weight: 700; margin-bottom: 1rem; }
.slide-content p { font-size: 1.1rem; line-height: 1.8; opacity: 0.95; }
.slider-btn {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,0.9); border: none; width: 48px; height: 48px;
    border-radius: 50%; cursor: pointer; font-size: 1.25rem; color: #2563eb;
    z-index: 10; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    display: flex; align-items: center; justify-content: center;
}
.slider-btn:hover { background: white; transform: translateY(-50%) scale(1.1); }
.slider-btn.prev { left: 1.5rem; }
.slider-btn.next { right: 1.5rem; }
.slider-dots { position: absolute; bottom: 1.5rem; left: 50%; transform: translateX(-50%); display: flex; gap: 0.6rem; z-index: 10; }
.slider-dot { width: 11px; height: 11px; border-radius: 50%; background: rgba(255,255,255,0.5); border: 2px solid white; cursor: pointer; transition: all 0.2s; }
.slider-dot.active { background: white; transform: scale(1.2); }

/* ── Feature cards ───────────────────────────────────────────────────────────── */
.features-section { background: #f9fafb; padding: 5rem 0; }
.features-section .inner { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
.features-section h2 { text-align: center; font-size: 2rem; font-weight: 700; color: #1f2937; margin-bottom: 0.75rem; }
.features-section .subtitle { text-align: center; color: #6b7280; font-size: 1.1rem; margin-bottom: 3rem; }
.feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.75rem; }
.feature-card {
    background: white; padding: 2rem; border-radius: 0.75rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07); transition: all 0.3s;
}
.feature-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
.feature-card i { font-size: 2.5rem; color: #2563eb; margin-bottom: 1rem; display: block; }
.feature-card:nth-child(3n+2) i { color: #10b981; }
.feature-card:nth-child(3n+3) i { color: #7c3aed; }
.feature-card h3 { font-size: 1.15rem; font-weight: 600; color: #1f2937; margin-bottom: 0.75rem; }
.feature-card p { color: #6b7280; line-height: 1.7; font-size: 0.95rem; }

/* ── Feature sections (alternating) ─────────────────────────────────────────── */
.feature-section { padding: 5rem 0; background: white; }
.feature-section:nth-child(even) { background: #f9fafb; }
.feature-section .inner {
    max-width: 1200px; margin: 0 auto; padding: 0 20px;
    display: grid; grid-template-columns: 1fr 1fr; gap: 5rem; align-items: center;
}
.feature-section.reverse .inner { direction: rtl; }
.feature-section.reverse .inner > * { direction: ltr; }
.feature-section .text h2 { font-size: 2.25rem; font-weight: 700; color: #1f2937; margin-bottom: 1.25rem; line-height: 1.3; }
.feature-section .text p { color: #4b5563; line-height: 1.8; font-size: 1.05rem; margin-bottom: 1rem; }
.feature-section .text p:last-child { margin-bottom: 0; }
.feature-section img { width: 100%; height: 420px; object-fit: cover; border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }

/* ── CTA ─────────────────────────────────────────────────────────────────────── */
.cta-section {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    color: white; padding: 5rem 0; text-align: center;
}
.cta-section h2 { font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; }
.cta-section p { font-size: 1.2rem; opacity: 0.9; margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto; }
.btn-hero-white { background: white; color: #2563eb; padding: 1rem 2.5rem; font-size: 1.1rem; font-weight: 700; border-radius: 0.5rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s; }
.btn-hero-white:hover { background: #f0f9ff; transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.2); }

/* ── Responsive ──────────────────────────────────────────────────────────────── */
@media (max-width: 968px) {
    .hero-content { grid-template-columns: 1fr; gap: 2.5rem; }
    .hero h1 { font-size: 2.25rem; }
    .hero-image img { height: 320px; }
    .feature-grid { grid-template-columns: 1fr 1fr; }
    .feature-section .inner { grid-template-columns: 1fr; gap: 2.5rem; }
    .feature-section.reverse .inner { direction: ltr; }
    .feature-section img { height: 280px; }
    .slider { height: 480px; }
    .slide-content { padding: 2rem 2rem 2rem 3rem; }
    .slide-content h3 { font-size: 1.75rem; }
}
@media (max-width: 640px) {
    .hero { padding: 3rem 0; }
    .hero h1 { font-size: 1.875rem; }
    .feature-grid { grid-template-columns: 1fr; }
    .cta-section h2 { font-size: 1.875rem; }
}
</style>

<!-- ── Hero ────────────────────────────────────────────────────────────────── -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-text">
            <h1>Structure Your Organisation.<br><span>Empower Your Teams.</span></h1>
            <p class="hero-subtitle">Team Service gives social care providers a single place to build and manage their organisational structure — from whole-organisation teams down to shift-level groups, with full membership history and role-based access.</p>
            <ul class="hero-features">
                <li><i class="fas fa-sitemap"></i> Build hierarchical team structures that mirror your real organisation</li>
                <li><i class="fas fa-user-plus"></i> Assign staff and people you support to any team</li>
                <li><i class="fas fa-id-badge"></i> Define roles and responsibilities within each team</li>
                <li><i class="fas fa-history"></i> Track membership over time with a full history log</li>
                <li><i class="fas fa-plug"></i> Integrates with your Staff Service and People Service</li>
                <li><i class="fas fa-shield-alt"></i> Role-based access control per team and organisation</li>
            </ul>
            <div class="hero-cta">
                <a href="<?php echo url('login.php'); ?>" class="btn-hero btn-hero-primary">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </a>
                <a href="<?php echo url('contact.php'); ?>" class="btn-hero btn-hero-outline">
                    <i class="fas fa-envelope"></i> Get in Touch
                </a>
            </div>
        </div>
        <div class="hero-image">
            <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=800&q=80"
                 alt="Team collaboration in a modern workplace">
        </div>
    </div>
</section>

<!-- ── Intro banner ───────────────────────────────────────────────────────── -->
<div class="intro-banner">
    <div class="container">
        <h2>One service. Every team in your organisation.</h2>
        <p>From care clusters and shift groups to whole-service departments — Team Service handles any structure, at any scale.</p>
    </div>
</div>

<!-- ── Feature slider ────────────────────────────────────────────────────── -->
<div class="slider-wrapper">
    <h2>Everything you need to manage team structure</h2>
    <div class="slider" id="slider">
        <div class="slides" id="slides">

            <div class="slide" style="background-image:url('https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1400&q=80')">
                <div class="slide-content">
                    <h3>Flexible Team Hierarchies</h3>
                    <p>Create teams for every layer of your organisation — departments, care clusters, shift teams, project groups. Nest them inside each other to mirror the way you actually work.</p>
                    <p>Parent-child relationships give managers clear visibility up and down the structure.</p>
                </div>
            </div>

            <div class="slide" style="background-image:url('https://images.unsplash.com/photo-1600880292203-757bb62b4baf?auto=format&fit=crop&w=1400&q=80')">
                <div class="slide-content">
                    <h3>Staff &amp; People Assignment</h3>
                    <p>Assign staff members and people you support to the teams they belong to. Record start dates, roles within the team, and primary team designation.</p>
                    <p>Full membership history means you always know who was in which team and when.</p>
                </div>
            </div>

            <div class="slide" style="background-image:url('https://images.unsplash.com/photo-1542744173-8e7e53415bb0?auto=format&fit=crop&w=1400&q=80')">
                <div class="slide-content">
                    <h3>Multi-Service Integration</h3>
                    <p>Team Service is designed to work alongside your Staff Service and People Service. Staff names and references stay in sync automatically whenever they change.</p>
                    <p>API-first architecture means any authorised service can query team data in real time.</p>
                </div>
            </div>

        </div>
        <button class="slider-btn prev" onclick="moveSlide(-1)"><i class="fas fa-chevron-left"></i></button>
        <button class="slider-btn next" onclick="moveSlide(1)"><i class="fas fa-chevron-right"></i></button>
        <div class="slider-dots" id="dots">
            <div class="slider-dot active" onclick="goToSlide(0)"></div>
            <div class="slider-dot" onclick="goToSlide(1)"></div>
            <div class="slider-dot" onclick="goToSlide(2)"></div>
        </div>
    </div>
</div>

<!-- ── Feature cards ──────────────────────────────────────────────────────── -->
<section class="features-section">
    <div class="inner">
        <h2>Built for social care</h2>
        <p class="subtitle">Every feature designed around the real-world needs of care organisations.</p>
        <div class="feature-grid">
            <div class="feature-card">
                <i class="fas fa-sitemap"></i>
                <h3>Unlimited Hierarchy Depth</h3>
                <p>Build as many levels of team nesting as your organisation requires — from a single team to a complex multi-tier structure.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-users-cog"></i>
                <h3>Flexible Team Types</h3>
                <p>Define your own team types — staff-only, mixed, shift-based — and configure which types appear in which parts of your system.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-id-badge"></i>
                <h3>Team Roles</h3>
                <p>Create roles specific to your organisation — Team Lead, Key Worker, Support Manager — and assign them to members within each team.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-history"></i>
                <h3>Membership History</h3>
                <p>Every membership change is logged with dates. See who left a team, when, and who replaced them — a complete audit trail.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-plug"></i>
                <h3>Open REST API</h3>
                <p>Query teams and members via a documented REST API. Secure API key authentication lets other services access data safely.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-building"></i>
                <h3>Multi-Organisation</h3>
                <p>Run multiple organisations from a single instance, each with their own teams, types, roles, and settings completely isolated.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Feature sections ───────────────────────────────────────────────────── -->
<section class="feature-section">
    <div class="inner">
        <div class="text">
            <h2>A structure that reflects how you work</h2>
            <p>No two care organisations work the same way. Team Service lets you build exactly the structure yours uses — whether that's home care patches, residential houses, day service groups, or a mix of all three.</p>
            <p>Teams can contain both staff and the people you support, or be staff-only, depending on how your organisation is set up. Team types make this flexible and easy to configure.</p>
        </div>
        <img src="https://images.unsplash.com/photo-1573497620053-ea5300f94f21?auto=format&fit=crop&w=700&q=80"
             alt="Professional reviewing organisational structure">
    </div>
</section>

<section class="feature-section reverse">
    <div class="inner">
        <div class="text">
            <h2>Know who's in every team, at every moment</h2>
            <p>Staff move between teams. People move between care arrangements. Team Service tracks all of it — recording join dates, leaving dates, and roles within each team.</p>
            <p>When you need to know who was responsible for a particular team at a particular time, the history is there. Not just who's current — who was there before, and for how long.</p>
        </div>
        <img src="https://images.unsplash.com/photo-1553484771-371a816b2772?auto=format&fit=crop&w=700&q=80"
             alt="Team discussion around a table">
    </div>
</section>

<section class="feature-section">
    <div class="inner">
        <div class="text">
            <h2>Connected to the rest of your digital ecosystem</h2>
            <p>Team Service is built to be one part of a connected system. It reads staff data from your Staff Service and people data from your People Service, keeping displayed names and references in sync without duplicating records.</p>
            <p>Any system that needs team data — reporting tools, scheduling apps, digital IDs — can query the API with a secure key. You control which external services have access and for which organisation.</p>
        </div>
        <img src="https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&w=700&q=80"
             alt="Digital integration and connected systems">
    </div>
</section>

<!-- ── CTA ────────────────────────────────────────────────────────────────── -->
<section class="cta-section">
    <div class="container">
        <h2>Ready to organise your teams?</h2>
        <p>Sign in to get started, or get in touch if you'd like to find out more about how Team Service can work for your organisation.</p>
        <a href="<?php echo url('login.php'); ?>" class="btn-hero-white">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </a>
        &nbsp;&nbsp;
        <a href="<?php echo url('contact.php'); ?>" style="color:rgba(255,255,255,0.85);text-decoration:none;font-size:1rem;margin-left:.5rem">
            or contact us <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</section>

<script>
var current = 0;
var total   = 3;
var timer;

function goToSlide(n) {
    current = n;
    document.getElementById('slides').style.transform = 'translateX(-' + (100/3 * n) + '%)';
    document.querySelectorAll('.slider-dot').forEach(function(d, i) {
        d.classList.toggle('active', i === n);
    });
}
function moveSlide(dir) {
    current = (current + dir + total) % total;
    goToSlide(current);
    resetTimer();
}
function resetTimer() {
    clearInterval(timer);
    timer = setInterval(function() { moveSlide(1); }, 6000);
}
resetTimer();
</script>

<?php include INCLUDES_PATH . '/public_footer.php'; ?>
