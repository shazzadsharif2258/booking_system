<footer class="mt-5 border-top bg-white">
  <div class="container py-4">
    <div class="row gy-3">
      <div class="col-md">
        <h6 class="fw-semibold">Plan Your Perfect Event!</h6>
        <small class="text-secondary">Book salons, spas, barbers & events with transparent pricing.</small>
      </div>
      <div class="col-md">
        <ul class="list-unstyled mb-0">
          <li><a class="link-secondary text-decoration-none" href="about.php">About Us</a></li>
          <li><a class="link-secondary text-decoration-none" href="services.php">Services</a></li>
          <li><a class="link-secondary text-decoration-none" href="support.php">Help & Support</a></li>
          <li><a class="link-secondary text-decoration-none" href="terms.php">T&C</a></li>
        </ul>
      </div>
      <div class="col-md d-flex flex-column align-items-md-end">
        <div class="mb-2 small text-secondary">Contact: <span class="fw-semibold text-body">+880 1984745679</span></div>
        <div class="d-flex gap-3 fs-5">
          <a class="link-secondary" href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a class="link-secondary" href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a class="link-secondary" href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
        </div>
      </div>
    </div>
    <div class="pt-3 mt-3 border-top small text-secondary">
      &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All rights reserved.
    </div>
  </div>
</footer>
<script>
  // reveal
  (function() {
    const obs = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('reveal-in');
          obs.unobserve(e.target);
        }
      });
    }, {
      threshold: .12
    });
    document.querySelectorAll('[data-reveal]').forEach(el => obs.observe(el));
  })();

  // ripple
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ripple');
    if (!btn) return;
    const d = document.createElement('span');
    const r = Math.max(btn.clientWidth, btn.clientHeight);
    d.style.cssText = `
      position:absolute; width:${r}px;height:${r}px; left:${e.offsetX - r/2}px; top:${e.offsetY - r/2}px;
      background:rgba(0,0,0,.08); border-radius:50%; transform:scale(0); animation:rip .6s ease-out forwards;
    `;
    btn.appendChild(d);
    setTimeout(() => d.remove(), 600);
  });
  const s = document.createElement('style');
  s.textContent = '@keyframes rip{to{transform:scale(1);opacity:0}}';
  document.head.appendChild(s);

  // gentle tilt (hero image card)
  const tilt = document.querySelector('.tilt');
  if (tilt) {
    tilt.addEventListener('mousemove', (e) => {
      const b = tilt.getBoundingClientRect(),
        x = (e.clientX - b.left) / b.width - .5,
        y = (e.clientY - b.top) / b.height - .5;
      tilt.style.transform = `rotateX(${ -y*3 }deg) rotateY(${ x*3 }deg)`;
    });
    tilt.addEventListener('mouseleave', () => tilt.style.transform = '');
  }
</script>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>