(function () {
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.10 });

    document.querySelectorAll('.reveal').forEach(function (element) {
        observer.observe(element);
    });
})();