<?php
declare(strict_types=1);
function front_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function front_date(?string $date, bool $time = false): string {
    if (!$date) return 'Date non renseignée';
    try { return (new DateTimeImmutable($date, new DateTimeZone('Europe/Paris')))->setTimezone(new DateTimeZone('Europe/Paris'))->format($time ? 'd.m.Y · H:i' : 'd.m.Y'); } catch (Throwable $e) { return 'Date non renseignée'; }
}
function front_kind(string $kind): string { return ['guide'=>'Le guide','analyse'=>'L’analyse','debrief'=>'Le débrief','coulisses'=>'Les coulisses'][$kind] ?? 'Le journal'; }
function front_body(string $body): string {
    $html='';foreach (preg_split('/\R\s*\R/',trim($body)) as $part) {
        if (strpos($part,'## ')===0 && strpos($part,"\n")===false) $html.='<h2>'.front_h(substr($part,3)).'</h2>';
        else $html.='<p>'.nl2br(front_h($part), false).'</p>';
    }return $html;
}
function front_link(string $path): string {
    $source=$GLOBALS['frontSource']??'direct';
    return front_h($path . ($source!=='direct' ? (strpos($path,'?')===false?'?':'&').'utm_source='.rawurlencode($source) : ''));
}
function front_arrow(): string { return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>'; }
function front_article_url(array $post): string { return '/article.php?slug='.rawurlencode($post['slug']); }
function front_journal_tiles(array $posts): void { foreach($posts as $i=>$post): $p=$post['data']; ?>
    <a class="story" href="<?= front_link(front_article_url($post)) ?>">
      <div class="story-art art-<?= front_h($p['kind']) ?>" aria-hidden="true"><span class="art-orbit"></span><span class="art-line"></span><span class="art-number"><?= str_pad((string)($i+1),2,'0',STR_PAD_LEFT) ?></span><span class="art-word"><?= ['guide'=>'DÉCODER','analyse'=>'DÉCIDER','debrief'=>'REVOIR','coulisses'=>'OBSERVER'][$p['kind']] ?></span></div>
      <div class="story-meta"><span><?= front_h(front_kind($p['kind'])) ?></span><time datetime="<?= front_h($post['publish_at']) ?>"><?= front_date($post['publish_at']) ?></time></div>
      <h3><?= front_h($p['title']) ?></h3><p><?= front_h($p['summary']) ?></p><span class="story-read">Lire l’article <?= front_arrow() ?></span>
    </a>
<?php endforeach; }
