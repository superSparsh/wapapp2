<script>
  (function () {
    try {
      var stored = localStorage.getItem('theme');
      var isDark = stored === 'dark';
      document.documentElement.classList.toggle('dark', isDark);
    } catch (e) {}
  })();
</script>
