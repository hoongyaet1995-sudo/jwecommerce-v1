(() => {
  const modal = document.getElementById('directorWalkthrough');
  if (!modal) return;
  const panels = [...modal.querySelectorAll('[data-director-step]')];
  const links = [...modal.querySelectorAll('[data-director-go]')];
  const previous = modal.querySelector('[data-director-prev]');
  const next = modal.querySelector('[data-director-next]');
  const finish = modal.querySelector('[data-director-finish]');
  let current = 0;
  const show = index => {
    current = Math.max(0, Math.min(panels.length - 1, index));
    panels.forEach((panel, i) => { panel.hidden = i !== current; });
    links.forEach((button, i) => {
      button.classList.toggle('ws-primary', i === current);
      button.classList.toggle('btn-light', i !== current);
      if (i === current) button.setAttribute('aria-current', 'step');
      else button.removeAttribute('aria-current');
    });
    previous.disabled = current === 0;
    next.hidden = current === panels.length - 1;
    finish.hidden = !next.hidden;
    modal.querySelector('[data-director-position]').textContent = `第${current + 1}段／共${panels.length}段`;
    modal.querySelector('.modal-body').scrollTop = 0;
  };
  previous.addEventListener('click', () => show(current - 1));
  next.addEventListener('click', () => show(current + 1));
  links.forEach(button => button.addEventListener('click', () => show(Number(button.dataset.directorGo))));
  modal.addEventListener('show.bs.modal', () => show(0));
  show(0);
})();
