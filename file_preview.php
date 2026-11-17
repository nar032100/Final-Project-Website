<?php
// Shared popup preview: opens PDFs and images (from /uploads) in a window instead of downloading.
// Included once by header.php and student_header.php.
?>
<style>
    #fpModal { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.65);
        align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
    #fpModal .fp-box { background: #ffffff; border-radius: 16px; width: 100%; max-width: 1000px; height: 100%; max-height: 92vh;
        display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3); }
    #fpModal .fp-bar { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #e9d5ff; background: #faf5ff; }
    #fpModal .fp-title { font-weight: 700; color: #581c87; flex: 1; font-size: 0.95rem; }
    #fpModal .fp-btn { background: #f3e8ff; color: #6b21a8; border: none; padding: 7px 12px; border-radius: 8px; font-size: 0.8rem;
        font-weight: 600; text-decoration: none; cursor: pointer; font-family: inherit; }
    #fpModal .fp-btn:hover { background: #e9d5ff; }
    #fpModal .fp-body { flex: 1; min-height: 0; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: auto; }
    #fpModal .fp-body iframe { width: 100%; height: 100%; border: 0; background: #ffffff; }
    #fpModal .fp-body img { max-width: 100%; max-height: 100%; object-fit: contain; }
    #fpModal .fp-hint { font-size: 0.75rem; color: #64748b; padding: 6px 16px; border-top: 1px solid #f1f5f9; background: #ffffff; }
</style>
<div id="fpModal" role="dialog" aria-modal="true" aria-label="File preview">
    <div class="fp-box">
        <div class="fp-bar">
            <span class="fp-title" id="fpTitle">File preview</span>
            <a class="fp-btn" id="fpOpen" href="#" target="_blank" rel="noopener">Open in new tab</a>
            <a class="fp-btn" id="fpDownload" href="#" download>Download</a>
            <button type="button" class="fp-btn" id="fpClose" aria-label="Close">&#10005; Close</button>
        </div>
        <div class="fp-body" id="fpBody"></div>
        <div class="fp-hint" id="fpHint" style="display:none;">If the PDF doesn't show on your device, use "Open in new tab".</div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('fpModal');
    var body = document.getElementById('fpBody');
    var hint = document.getElementById('fpHint');
    var re = /\.(pdf|jpe?g|png|gif|webp)(\?.*)?$/i;

    function closePreview() {
        modal.style.display = 'none';
        body.innerHTML = '';
        document.body.style.overflow = '';
    }
    function showPreview(href, ext) {
        body.innerHTML = '';
        if (ext === 'pdf') {
            var f = document.createElement('iframe');
            f.src = href;
            f.title = 'PDF preview';
            body.appendChild(f);
            hint.style.display = 'block';
        } else {
            var img = document.createElement('img');
            img.src = href;
            img.alt = 'Image preview';
            body.appendChild(img);
            hint.style.display = 'none';
        }
        document.getElementById('fpTitle').textContent = ext === 'pdf' ? 'PDF preview' : 'Image preview';
        document.getElementById('fpOpen').href = href;
        document.getElementById('fpDownload').href = href;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.closest('#fpModal')) return;
        var href = a.getAttribute('href') || '';
        if (href.indexOf('uploads/') !== 0 && href.indexOf('/uploads/') !== 0) return;
        var m = href.match(re);
        if (!m) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
        e.preventDefault();
        showPreview(href, m[1].toLowerCase());
    });
    document.getElementById('fpClose').addEventListener('click', closePreview);
    modal.addEventListener('click', function (e) { if (e.target === modal) closePreview(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.style.display === 'flex') closePreview(); });
})();
</script>