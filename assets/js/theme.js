(() => {
    const htmlElement = document.documentElement;
    const systemDarkMode = window.matchMedia('(prefers-color-scheme: dark)');
    const defaultTheme = systemDarkMode.matches ? 'dark' : 'light';

    const setTheme = (theme) => {
        htmlElement.setAttribute('data-bs-theme', theme);
    }

    setTheme(defaultTheme);

    systemDarkMode.addEventListener('change', (e) => {
        const newTheme = e.matches ? 'dark' : 'light';
        setTheme(newTheme);
    })
})();
