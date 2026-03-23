<?php
require_once dirname(__DIR__) . '/config/config.php';

$pageTitle = 'Our Platform';
include INCLUDES_PATH . '/public_header.php';
?>

<style>
.services-wrap { max-width: 1100px; margin: 0 auto; padding: 4rem 20px 6rem; }
.services-hero { text-align: center; margin-bottom: 4rem; }
.services-hero h1 { font-size: 2.5rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem; }
.services-hero p { font-size: 1.15rem; color: #6b7280; max-width: 640px; margin: 0 auto; line-height: 1.7; }

.services-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    margin-bottom: 4rem;
}
@media (max-width: 900px) { .services-grid { grid-template-columns: 1fr; } }

.service-card {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 2px 16px rgba(0,0,0,0.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s, box-shadow 0.2s;
    border: 2px solid transparent;
}
.service-card:hover { transform: translateY(-4px); box-shadow: 0 8px 32px rgba(0,0,0,0.12); }
.service-card.current { border-color: #2563eb; }

.service-card-header {
    padding: 2rem 2rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}
.service-icon {
    width: 3.5rem; height: 3.5rem; border-radius: 0.75rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; flex-shrink: 0;
}
.service-icon.staff  { background: #fef3c7; color: #d97706; }
.service-icon.team   { background: #dbeafe; color: #2563eb; }
.service-icon.people { background: #f3e8ff; color: #7c3aed; }

.service-card-header h2 { font-size: 1.25rem; font-weight: 700; color: #1f2937; margin: 0; }
.service-card-header .badge-current {
    font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
    background: #dbeafe; color: #1d4ed8; padding: 0.2rem 0.5rem; border-radius: 999px;
    margin-left: auto; white-space: nowrap;
}

.service-card-body { padding: 0 2rem 1.5rem; flex: 1; }
.service-card-body p { color: #4b5563; line-height: 1.65; margin-bottom: 1.25rem; font-size: 0.95rem; }

.service-features { list-style: none; padding: 0; margin: 0 0 1.5rem; }
.service-features li {
    display: flex; align-items: flex-start; gap: 0.6rem;
    color: #374151; font-size: 0.9rem; padding: 0.3rem 0;
}
.service-features li i { color: #10b981; margin-top: 0.1rem; flex-shrink: 0; }

.service-card-footer { padding: 1.25rem 2rem; border-top: 1px solid #f3f4f6; }
.service-card-footer .btn-visit {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.65rem 1.5rem; border-radius: 0.5rem; font-weight: 600;
    font-size: 0.95rem; text-decoration: none; transition: background 0.2s, transform 0.15s;
}
.service-card-footer .btn-visit:hover { transform: translateX(2px); }
.btn-visit.staff  { background: #d97706; color: white; }
.btn-visit.staff:hover  { background: #b45309; }
.btn-visit.team   { background: #2563eb; color: white; }
.btn-visit.team:hover   { background: #1d4ed8; }
.btn-visit.people { background: #7c3aed; color: white; }
.btn-visit.people:hover { background: #6d28d9; }
.btn-visit.disabled { background: #e5e7eb; color: #9ca3af; cursor: default; pointer-events: none; }

.how-it-connects { background: #f8fafc; border-radius: 1rem; padding: 3rem 2rem; text-align: center; margin-top: 2rem; }
.how-it-connects h2 { font-size: 1.75rem; font-weight: 700; color: #1f2937; margin-bottom: 0.75rem; }
.how-it-connects > p { color: #6b7280; max-width: 600px; margin: 0 auto 2.5rem; line-height: 1.7; }

.flow-diagram {
    display: flex; align-items: center; justify-content: center;
    flex-wrap: wrap; gap: 0.5rem; margin-bottom: 2rem;
}
.flow-node {
    display: flex; flex-direction: column; align-items: center; gap: 0.4rem;
    background: white; border-radius: 0.75rem; padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07); min-width: 9rem;
}
.flow-node i { font-size: 1.75rem; }
.flow-node.staff i  { color: #d97706; }
.flow-node.team i   { color: #2563eb; }
.flow-node.people i { color: #7c3aed; }
.flow-node span { font-size: 0.85rem; font-weight: 600; color: #374151; }
.flow-arrow { color: #9ca3af; font-size: 1.25rem; }
</style>

<div class="services-wrap">

    <div class="services-hero">
        <h1><i class="fas fa-cubes" style="color:#2563eb;margin-right:.5rem"></i> Our Platform</h1>
        <p>Three connected services working together — staff management, team structure, and care for people you support. Each service is a specialist tool that integrates seamlessly with the others.</p>
    </div>

    <div class="services-grid">

        <!-- Staff Service -->
        <div class="service-card">
            <div class="service-card-header">
                <div class="service-icon staff"><i class="fas fa-id-card-clip"></i></div>
                <div>
                    <h2>Staff Service</h2>
                </div>
            </div>
            <div class="service-card-body">
                <p>The single source of truth for your staff and employee data. Manage complete profiles, track training and learning records, run appraisals and supervisions, and connect with HR systems.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Complete staff profiles &amp; employment history</li>
                    <li><i class="fas fa-check-circle"></i> Training &amp; learning records</li>
                    <li><i class="fas fa-check-circle"></i> Appraisals &amp; supervisions</li>
                    <li><i class="fas fa-check-circle"></i> Microsoft Entra / 365 integration</li>
                    <li><i class="fas fa-check-circle"></i> Recruitment pipeline &amp; digital ID cards</li>
                    <li><i class="fas fa-check-circle"></i> REST API for downstream systems</li>
                </ul>
            </div>
            <div class="service-card-footer">
                <?php if (STAFF_SERVICE_URL): ?>
                <a href="<?php echo htmlspecialchars(STAFF_SERVICE_URL); ?>/landing.php" class="btn-visit staff" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-arrow-right"></i> Visit Staff Service
                </a>
                <?php else: ?>
                <span class="btn-visit disabled"><i class="fas fa-link-slash"></i> Not configured</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Team Service (current) -->
        <div class="service-card current">
            <div class="service-card-header">
                <div class="service-icon team"><i class="fas fa-people-group"></i></div>
                <div>
                    <h2>Team Service</h2>
                </div>
                <span class="badge-current">You are here</span>
            </div>
            <div class="service-card-body">
                <p>Structure your whole organisation with flexible team hierarchies. Assign staff and people you support to teams, manage team memberships, and share team data across the platform via API.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Unlimited team hierarchy depth</li>
                    <li><i class="fas fa-check-circle"></i> Flexible team types &amp; parent/child nesting</li>
                    <li><i class="fas fa-check-circle"></i> Staff &amp; people membership management</li>
                    <li><i class="fas fa-check-circle"></i> Named team roles</li>
                    <li><i class="fas fa-check-circle"></i> Full membership history</li>
                    <li><i class="fas fa-check-circle"></i> Open REST API</li>
                </ul>
            </div>
            <div class="service-card-footer">
                <a href="<?php echo url('landing.php'); ?>" class="btn-visit team">
                    <i class="fas fa-gauge"></i> Go to Dashboard
                </a>
            </div>
        </div>

        <!-- People Service -->
        <div class="service-card">
            <div class="service-card-header">
                <div class="service-icon people"><i class="fas fa-heart-pulse"></i></div>
                <div>
                    <h2>People Service</h2>
                </div>
            </div>
            <div class="service-card-body">
                <p>Person-centred care management for the people you support. Build complete profiles, track care needs, assign key workers, and keep emergency contacts up to date — all in one secure place.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Rich person profiles</li>
                    <li><i class="fas fa-check-circle"></i> Categorised care needs tracking</li>
                    <li><i class="fas fa-check-circle"></i> Key worker relationships</li>
                    <li><i class="fas fa-check-circle"></i> Emergency contacts</li>
                    <li><i class="fas fa-check-circle"></i> Staff Service integration</li>
                    <li><i class="fas fa-check-circle"></i> Multi-organisation support</li>
                </ul>
            </div>
            <div class="service-card-footer">
                <?php if (PEOPLE_SERVICE_URL): ?>
                <a href="<?php echo htmlspecialchars(PEOPLE_SERVICE_URL); ?>/landing.php" class="btn-visit people" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-arrow-right"></i> Visit People Service
                </a>
                <?php else: ?>
                <span class="btn-visit disabled"><i class="fas fa-link-slash"></i> Not configured</span>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="how-it-connects">
        <h2>How the Services Connect</h2>
        <p>Each service owns its own domain of data and exposes a REST API. The services read from each other to enrich their views — staff records flow into team membership, and team structure flows into care management.</p>
        <div class="flow-diagram">
            <div class="flow-node staff">
                <i class="fas fa-id-card-clip"></i>
                <span>Staff Service</span>
            </div>
            <div class="flow-arrow"><i class="fas fa-arrows-left-right"></i></div>
            <div class="flow-node team">
                <i class="fas fa-people-group"></i>
                <span>Team Service</span>
            </div>
            <div class="flow-arrow"><i class="fas fa-arrows-left-right"></i></div>
            <div class="flow-node people">
                <i class="fas fa-heart-pulse"></i>
                <span>People Service</span>
            </div>
        </div>
        <p style="font-size:.9rem;color:#9ca3af">Each service is independent and works standalone — integration is optional and configured per organisation.</p>
    </div>

</div>

<?php include INCLUDES_PATH . '/public_footer.php'; ?>
