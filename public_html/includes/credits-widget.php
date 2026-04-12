<?php
// STRATEDGE — Widget crédits réutilisable (dashboard + sidebar)
require_once __DIR__ . '/credits-manager.php';

function stratedge_render_credits_widget(int $membreId): string {
    $solde = stratedge_credits_solde($membreId);
    $historique = stratedge_credits_historique($membreId, 5);
    $lowCredit = ($solde <= 2);
    $color = $solde === 0 ? '#ff2d7a' : ($lowCredit ? '#ffc107' : '#00d4ff');

    $html = '<div style="background:linear-gradient(180deg,rgba(20,15,30,0.95),rgba(10,10,25,0.95));border:1px solid rgba(0,212,255,0.25);border-radius:16px;padding:1.5rem;margin:1rem 0;color:#fff;font-family:\'Rajdhani\',sans-serif">';
    $html .= '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">';
    $html .= '<div><div style="font-family:\'Orbitron\',sans-serif;font-size:.75rem;color:rgba(255,255,255,.5);letter-spacing:1.5px;text-transform:uppercase">💎 Mes crédits</div>';
    $html .= '<div style="font-family:\'Bebas Neue\',sans-serif;font-size:3rem;line-height:1;color:' . $color . '">' . $solde . '</div>';
    $html .= '<div style="font-size:.85rem;color:rgba(255,255,255,.6)">' . ($solde > 1 ? 'paris disponibles' : 'pari disponible') . '</div></div>';
    $html .= '<a href="/packs-daily.php" style="padding:.7rem 1.2rem;background:linear-gradient(135deg,#ff2d7a,#c850c0);color:#fff;text-decoration:none;border-radius:10px;font-family:\'Orbitron\',sans-serif;font-size:.75rem;font-weight:700;letter-spacing:1px">+ RECHARGER</a>';
    $html .= '</div>';

    if ($lowCredit && $solde > 0) {
        $html .= '<div style="background:rgba(255,193,7,0.08);border-left:3px solid #ffc107;padding:.7rem 1rem;border-radius:6px;font-size:.85rem;color:#ffc107;margin-bottom:1rem">⚠️ Plus que ' . $solde . ' crédit' . ($solde > 1 ? 's' : '') . ' — pense à recharger</div>';
    }

    if (!empty($historique)) {
        $html .= '<details style="cursor:pointer"><summary style="font-size:.8rem;color:rgba(255,255,255,.6);font-family:\'Orbitron\',sans-serif">Historique des packs</summary>';
        $html .= '<div style="margin-top:.8rem;font-size:.85rem">';
        foreach ($historique as $h) {
            $date = date('d/m/Y', strtotime($h['date_achat']));
            $html .= '<div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.05)">';
            $html .= '<span>' . ucfirst($h['pack_type']) . ' · ' . $h['nb_initial'] . ' paris</span>';
            $html .= '<span style="color:rgba(255,255,255,.5)">' . $h['nb_restants'] . '/' . $h['nb_initial'] . ' · ' . $date . '</span>';
            $html .= '</div>';
        }
        $html .= '</div></details>';
    }

    $html .= '</div>';
    return $html;
}

// Badge compact pour navbar
function stratedge_render_credits_badge(int $membreId): string {
    $solde = stratedge_credits_solde($membreId);
    if ($solde === 0) return '';
    $color = $solde <= 2 ? '#ffc107' : '#00d4ff';
    return '<a href="/packs-daily.php" style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .8rem;background:rgba(0,212,255,.08);border:1px solid ' . $color . ';border-radius:20px;color:' . $color . ';text-decoration:none;font-family:\'Orbitron\',sans-serif;font-size:.7rem;font-weight:700">💎 ' . $solde . '</a>';
}
