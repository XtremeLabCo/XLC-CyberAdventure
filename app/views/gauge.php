<?php
// Password strength shown as a speedometer-style gauge. The needle angle is set via the --needle CSS variable (-90deg = 0, 90deg = 100).
// Colour zones match the password-strength.js tiers: 28 / 40 / 55 bits = 40 / 57 / 79 points.
$gaugeCompact = $gaugeCompact ?? false;
$point = static fn(float $score, float $radius): string => sprintf('%.2f %.2f', 100 + $radius * cos(M_PI * (1 - $score / 100)), 100 - $radius * sin(M_PI * (1 - $score / 100)));
$zones = [[0, 40, 'weak'], [40, 57, 'fair'], [57, 79, 'good'], [79, 100, 'strong']];
?>
<svg class="gauge<?= $gaugeCompact ? ' gauge-compact' : '' ?>" viewBox="0 0 200 <?= $gaugeCompact ? 112 : 124 ?>" aria-hidden="true" focusable="false">
<?php foreach ($zones as [$from, $to, $zone]): ?>
  <path class="gauge-zone gauge-<?= $zone ?>" d="M<?= $point($from + ($from ? 0.9 : 0), 80) ?> A80 80 0 0 1 <?= $point($to - ($to < 100 ? 0.9 : 0), 80) ?>"/>
<?php endforeach; ?>
<?php if (!$gaugeCompact): for ($score = 0; $score <= 100; $score += 10): [$x1, $y1] = explode(' ', $point($score, 62)); [$x2, $y2] = explode(' ', $point($score, 68)); ?>
  <line class="gauge-tick<?= $score % 50 === 0 ? ' gauge-tick-major' : '' ?>" x1="<?= $x1 ?>" y1="<?= $y1 ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>"/>
<?php endfor; ?>
  <text class="gauge-end" x="20" y="120" text-anchor="middle">Weak</text>
  <text class="gauge-end" x="180" y="120" text-anchor="middle">Strong</text>
<?php endif; ?>
  <g class="gauge-needle"><path d="M100 100 L96.5 97 L100 <?= $gaugeCompact ? 34 : 30 ?> L103.5 97 Z"/></g>
  <circle class="gauge-hub" cx="100" cy="100" r="<?= $gaugeCompact ? 9 : 7.5 ?>"/>
</svg>
