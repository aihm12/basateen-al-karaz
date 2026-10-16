document.addEventListener('DOMContentLoaded',()=>{const board=document.querySelector('[data-refresh]');if(board){setTimeout(()=>{if(!document.hidden)location.reload();else document.addEventListener('visibilitychange',()=>{if(!document.hidden)location.reload();},{once:true});},30000);}});

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[type="password"]').forEach((input, index) => {
    if (!input.id) input.id = 'password-field-' + index;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'link-btn password-toggle';
    button.textContent = 'إظهار كلمة المرور';
    button.setAttribute('aria-controls', input.id);
    button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.textContent = show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور';
      button.setAttribute('aria-pressed', String(show));
    });
    input.insertAdjacentElement('afterend', button);
  });
});
