(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState !== 'loading') {
            callback();
            return;
        }
        document.addEventListener('DOMContentLoaded', callback);
    }

    ready(function () {
        // ----- Alertas: fechar e esconder automaticamente -----
        document.querySelectorAll('[data-dismiss]').forEach(function (button) {
            button.addEventListener('click', function () {
                var alert = button.closest('.alert');
                if (alert) {
                    alert.style.transition = 'opacity .2s ease';
                    alert.style.opacity = '0';
                    setTimeout(function () { alert.remove(); }, 200);
                }
            });
        });

        document.querySelectorAll('.flash-stack .alert--success, .flash-stack .alert--info').forEach(function (alert) {
            setTimeout(function () {
                alert.style.transition = 'opacity .35s ease';
                alert.style.opacity = '0';
                setTimeout(function () { alert.remove(); }, 350);
            }, 7000);
        });

        // ----- Menu mobile da loja -----
        var mobileNav = document.querySelector('[data-mobile-nav]');
        document.querySelectorAll('[data-open-nav]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (mobileNav) {
                    mobileNav.classList.add('is-open');
                    document.body.style.overflow = 'hidden';
                }
            });
        });
        document.querySelectorAll('[data-close-nav]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (mobileNav) {
                    mobileNav.classList.remove('is-open');
                    document.body.style.overflow = '';
                }
            });
        });

        // ----- Sidebar do admin -----
        var adminSidebar = document.querySelector('[data-admin-sidebar]');
        document.querySelectorAll('[data-toggle-admin-nav]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (adminSidebar) {
                    adminSidebar.classList.toggle('is-open');
                }
            });
        });

        // ----- Slider de banners -----
        document.querySelectorAll('[data-hero]').forEach(function (hero) {
            var track = hero.querySelector('[data-hero-track]');
            var slides = hero.querySelectorAll('.hero__slide');
            var dots = hero.querySelectorAll('.hero__dot');
            if (!track || slides.length < 2) {
                return;
            }

            var current = 0;
            var timer = null;

            function goTo(index) {
                current = (index + slides.length) % slides.length;
                track.style.transform = 'translateX(-' + (current * 100) + '%)';
                dots.forEach(function (dot, dotIndex) {
                    dot.classList.toggle('is-active', dotIndex === current);
                });
            }

            function start() {
                timer = setInterval(function () { goTo(current + 1); }, 6500);
            }

            function restart() {
                clearInterval(timer);
                start();
            }

            dots.forEach(function (dot) {
                dot.addEventListener('click', function () {
                    goTo(parseInt(dot.getAttribute('data-slide') || '0', 10));
                    restart();
                });
            });

            hero.addEventListener('mouseenter', function () { clearInterval(timer); });
            hero.addEventListener('mouseleave', start);
            start();
        });

        // ----- Galeria do produto -----
        var galleryMain = document.querySelector('[data-gallery-main] img');
        document.querySelectorAll('[data-gallery-thumb]').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var src = thumb.getAttribute('data-src');
                if (galleryMain && src) {
                    galleryMain.src = src;
                }
                document.querySelectorAll('[data-gallery-thumb]').forEach(function (item) {
                    item.classList.remove('is-active');
                });
                thumb.classList.add('is-active');
            });
        });

        // ----- Abas do produto -----
        document.querySelectorAll('[data-product-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-product-tab');
                document.querySelectorAll('[data-product-tab]').forEach(function (item) {
                    item.classList.remove('is-active');
                });
                document.querySelectorAll('[data-product-panel]').forEach(function (panel) {
                    panel.classList.remove('is-active');
                });
                tab.classList.add('is-active');
                var panel = document.querySelector('[data-product-panel="' + target + '"]');
                if (panel) {
                    panel.classList.add('is-active');
                }
            });
        });

        // ----- Controle de quantidade -----
        document.querySelectorAll('[data-qty-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                var wrapper = button.closest('.qty-stepper');
                var input = wrapper ? wrapper.querySelector('input') : null;
                if (!input) {
                    return;
                }
                var value = parseInt(input.value || '1', 10) + parseInt(button.getAttribute('data-qty-step') || '0', 10);
                var min = parseInt(input.getAttribute('min') || '1', 10);
                var max = parseInt(input.getAttribute('max') || '99', 10);
                value = Math.max(min, Math.min(max, value));
                input.value = value;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        // ----- Confirmação para ações destrutivas -----
        document.querySelectorAll('[data-confirm]').forEach(function (element) {
            element.addEventListener('click', function (event) {
                var message = element.getAttribute('data-confirm') || 'Confirmar esta ação?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            });
        });

        // ----- Loading em formulários -----
        document.querySelectorAll('form[data-loading]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('button[type="submit"]');
                if (button) {
                    button.classList.add('is-loading');
                }
            });
        });

        // ----- Auto submit de filtros -----
        document.querySelectorAll('[data-auto-submit]').forEach(function (element) {
            element.addEventListener('change', function () {
                var form = element.closest('form');
                if (form) {
                    form.submit();
                }
            });
        });

        // ----- Checkout: atualizar resumo ao trocar frete -----
        var checkoutSummary = document.querySelector('[data-checkout-summary]');
        if (checkoutSummary) {
            var subtotal = parseFloat(checkoutSummary.getAttribute('data-subtotal') || '0');
            var discount = parseFloat(checkoutSummary.getAttribute('data-discount') || '0');
            var shippingLabel = document.querySelector('[data-checkout-shipping]');
            var totalLabel = document.querySelector('[data-checkout-total]');

            function money(value) {
                return 'R$ ' + value.toFixed(2).replace('.', ',');
            }

            function updateSummary() {
                var selected = document.querySelector('input[name="shipping_method"]:checked');
                var cost = selected ? parseFloat(selected.getAttribute('data-cost') || '0') : 0;
                var total = Math.max(0, subtotal - discount) + cost;
                if (shippingLabel) {
                    shippingLabel.textContent = cost > 0 ? money(cost) : 'Grátis';
                }
                if (totalLabel) {
                    totalLabel.textContent = money(total);
                }
            }

            document.querySelectorAll('input[name="shipping_method"]').forEach(function (radio) {
                radio.addEventListener('change', updateSummary);
            });
        }

        // ----- Pré-visualização de imagens no admin -----
        document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
            input.addEventListener('change', function () {
                var target = document.querySelector(input.getAttribute('data-preview'));
                if (!target) {
                    return;
                }
                target.innerHTML = '';
                Array.prototype.slice.call(input.files).forEach(function (file) {
                    if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
                        return;
                    }
                    var image = document.createElement('img');
                    image.src = URL.createObjectURL(file);
                    image.alt = 'Pré-visualização';
                    target.appendChild(image);
                });
            });
        });

        // ----- Mostrar/ocultar senha -----
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                var field = document.querySelector(button.getAttribute('data-toggle-password'));
                if (field) {
                    field.type = field.type === 'password' ? 'text' : 'password';
                }
            });
        });
    });
})();
