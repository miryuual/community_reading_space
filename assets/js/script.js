document.addEventListener('click', function (event) {
  const target = event.target.closest('[data-confirm]');
  if (target && !confirm(target.getAttribute('data-confirm'))) event.preventDefault();
});

// Preview header image on file selection
document.addEventListener('DOMContentLoaded', function(){ const input=document.getElementById('crs_header_image'); const preview=document.getElementById('crs-header-live-preview'); if(input&&preview){ input.addEventListener('change', function(){ const file=input.files&&input.files[0]; if(!file){preview.hidden=true;return;} if(!file.type.startsWith('image/')){preview.hidden=true;return;} preview.src=URL.createObjectURL(file); preview.hidden=false; }); } });
