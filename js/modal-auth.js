/**
 * Modal de Autenticación - Login/Registro/Recupero
 * Migrado desde snippet "Modal Auth v3.0 (LiteSpeed Optimized)"
 * Optimizado para WC-AJAX y performance
 */

(function() {
    'use strict';

    // Verificar que el modal existe
    const modal = document.getElementById('mu-auth-modal');
    if (!modal) return;

    /* =====================
       CACHE DE ELEMENTOS DOM
       ===================== */
    const elements = {
        close: modal.querySelector('.mu-modal-close'),
        overlay: modal.querySelector('.mu-modal-overlay'),
        
        steps: {
            initial: document.getElementById('mu-step-1'),
            login: document.getElementById('mu-step-2-login'),
            register: document.getElementById('mu-step-2-register'),
            forgot: document.getElementById('mu-step-forgot')
        },
        
        inputs: {
            user: document.getElementById('mu-user-input'),
            passwordLogin: document.getElementById('mu-password-login'),
            passwordRegister: document.getElementById('mu-password-register'),
            emailRegister: document.getElementById('mu-email-register'),
            emailGroup: document.getElementById('mu-email-group'),
            forgotEmail: document.getElementById('mu-forgot-email')
        },
        
        buttons: {
            continue: document.getElementById('mu-continue-btn'),
            login: document.getElementById('mu-login-btn'),
            register: document.getElementById('mu-register-btn'),
            reset: document.getElementById('mu-send-reset-btn'),
            backToStep1: document.getElementById('mu-back-to-step1'),
            backToStep1Reg: document.getElementById('mu-back-to-step1-reg'),
            backToLogin: document.getElementById('mu-back-to-login')
        },
        
        ui: {
            message: document.getElementById('mu-auth-message'),
            userDisplay: document.getElementById('mu-user-display'),
            title: document.getElementById('mu-modal-title'),
            subtitle: document.getElementById('mu-modal-subtitle'),
            social: document.getElementById('mu-social-section'),
            forgotLink: document.getElementById('mu-forgot-link')
        },
        
        form: document.getElementById('mu-auth-form')
    };

    /* =====================
       ESTADO DE LA APLICACIÓN
       ===================== */
    let state = {
        user: '',
        isEmail: false
    };

    /* =====================
       UTILIDADES
       ===================== */
    const utils = {
        showMessage: (text, type) => {
            elements.ui.message.textContent = text;
            elements.ui.message.className = `mu-auth-message ${type}`;
            elements.ui.message.style.display = 'block';
        },
        
        hideMessage: () => {
            elements.ui.message.style.display = 'none';
        },
        
        validateEmail: (email) => {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },
        
        /**
         * Helper para llamadas WC-AJAX (más rápido que admin-ajax)
         * @param {string} action - Acción WC-AJAX (mu_check_user, mu_login_user, etc.)
         * @param {object} data - Datos a enviar
         * @returns {Promise}
         */
        wcAjax: (action, data) => {
            // Verificar que muAuthData esté disponible (localizado en PHP)
            if (typeof muAuthData === 'undefined') {
                console.error('muAuthData no está disponible');
                return Promise.reject(new Error('Configuración no disponible'));
            }
            
            // Construir URL reemplazando placeholder
            const url = muAuthData.ajax_url.replace('%%endpoint%%', action);
            
            // Preparar form data
            const formData = new URLSearchParams();
            formData.append('nonce', muAuthData.nonce);
            
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    formData.append(key, data[key]);
                }
            }
            
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            }).then(response => response.json());
        }
    };

    /* =====================
       GESTIÓN DEL MODAL
       ===================== */
    const modalManager = {
        open: () => {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Auto-rellenar desde billing_email si existe (checkout)
            const billingEmail = document.getElementById('billing_email');
            if (billingEmail && billingEmail.value.trim()) {
                elements.inputs.user.value = billingEmail.value.trim();
                setTimeout(() => elements.buttons.continue.click(), 100);
            } else {
                setTimeout(() => elements.inputs.user.focus(), 100);
            }
        },
        
        close: () => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            modalManager.reset();
        },
        
        reset: () => {
            // Ocultar todos los pasos
            Object.values(elements.steps).forEach(step => {
                if (step) step.style.display = 'none';
            });
            
            // Mostrar paso inicial
            elements.steps.initial.style.display = 'block';
            elements.ui.social.style.display = 'block';
            
            // Restaurar textos
            elements.ui.title.textContent = '¡Te damos la bienvenida!';
            elements.ui.subtitle.textContent = 'Ingresa a tu cuenta o creá una nueva';
            
            // Limpiar inputs
            Object.values(elements.inputs).forEach(input => {
                if (input) input.value = '';
            });
            
            // Limpiar mensajes
            utils.hideMessage();
        }
    };

    /* =====================
       PASO 1: VERIFICAR USUARIO
       ===================== */
    const handleContinue = () => {
        const userInput = elements.inputs.user.value.trim();
        
        if (!userInput) {
            utils.showMessage('Ingresá tu email o usuario', 'error');
            return;
        }
        
        utils.hideMessage();
        
        // Deshabilitar botón durante la petición
        elements.buttons.continue.disabled = true;
        elements.buttons.continue.textContent = '...';
        
        // Guardar estado
        state.user = userInput;
        state.isEmail = utils.validateEmail(userInput);
        
        // Llamada AJAX
        utils.wcAjax('mu_check_user', { user_input: userInput })
            .then(response => {
                elements.buttons.continue.disabled = false;
                elements.buttons.continue.textContent = 'Continuar';
                
                // Ocultar paso 1
                elements.steps.initial.style.display = 'none';
                
                if (response.success && response.data.exists) {
                    // Usuario existe -> Mostrar login
                    elements.ui.userDisplay.textContent = response.data.display_name || userInput;
                    elements.steps.login.style.display = 'block';
                    setTimeout(() => elements.inputs.passwordLogin.focus(), 100);
                } else {
                    // Usuario no existe -> Mostrar registro
                    elements.steps.register.style.display = 'block';
                    
                    // Si no es email, mostrar campo de email
                    if (!state.isEmail) {
                        elements.inputs.emailGroup.style.display = 'block';
                        elements.inputs.emailRegister.required = true;
                        setTimeout(() => elements.inputs.emailRegister.focus(), 100);
                    } else {
                        elements.inputs.emailGroup.style.display = 'none';
                        elements.inputs.emailRegister.required = false;
                        setTimeout(() => elements.inputs.passwordRegister.focus(), 100);
                    }
                }
            })
            .catch(error => {
                elements.buttons.continue.disabled = false;
                elements.buttons.continue.textContent = 'Continuar';
                utils.showMessage('Error de conexión', 'error');
                console.error('Error:', error);
            });
    };

    /* =====================
       LOGIN
       ===================== */
    const handleLogin = () => {
        const password = elements.inputs.passwordLogin.value;
        
        if (!password) {
            utils.showMessage('Ingresá tu contraseña', 'error');
            return;
        }
        
        elements.buttons.login.disabled = true;
        elements.buttons.login.textContent = 'Ingresando...';
        
        utils.wcAjax('mu_login_user', {
            user_login: state.user,
            password: password
        })
        .then(response => {
            if (response.success) {
                utils.showMessage('¡Bienvenido!', 'success');
                setTimeout(() => window.location.reload(), 500);
            } else {
                elements.buttons.login.disabled = false;
                elements.buttons.login.textContent = 'Entrar';
                utils.showMessage(response.data.message || 'Contraseña incorrecta', 'error');
            }
        })
        .catch(error => {
            elements.buttons.login.disabled = false;
            elements.buttons.login.textContent = 'Entrar';
            utils.showMessage('Error de conexión', 'error');
            console.error('Error:', error);
        });
    };

    /* =====================
       REGISTRO
       ===================== */
    const handleRegister = () => {
        const password = elements.inputs.passwordRegister.value;
        const email = state.isEmail ? state.user : elements.inputs.emailRegister.value.trim();
        
        if (password.length < 6) {
            utils.showMessage('Mínimo 6 caracteres', 'error');
            return;
        }
        
        if (!email || !utils.validateEmail(email)) {
            utils.showMessage('Email válido requerido', 'error');
            return;
        }
        
        elements.buttons.register.disabled = true;
        elements.buttons.register.textContent = 'Creando...';
        
        // Generar username desde email si el input era email
        const username = state.isEmail ? email.split('@')[0] : state.user;
        
        utils.wcAjax('mu_register_user', {
            username: username,
            email: email,
            password: password
        })
        .then(response => {
            if (response.success) {
                utils.showMessage('¡Cuenta creada!', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                elements.buttons.register.disabled = false;
                elements.buttons.register.textContent = 'Crear cuenta';
                utils.showMessage(response.data.message || 'Error al crear cuenta', 'error');
            }
        })
        .catch(error => {
            elements.buttons.register.disabled = false;
            elements.buttons.register.textContent = 'Crear cuenta';
            utils.showMessage('Error de conexión', 'error');
            console.error('Error:', error);
        });
    };

    /* =====================
       RECUPERAR CONTRASEÑA
       ===================== */
    const handleResetPassword = () => {
        const userLogin = elements.inputs.forgotEmail.value.trim();
        
        if (!userLogin) {
            utils.showMessage('Ingresá tu email', 'error');
            return;
        }
        
        elements.buttons.reset.disabled = true;
        elements.buttons.reset.textContent = 'Enviando...';
        
        utils.wcAjax('mu_reset_password', { user_login: userLogin })
            .then(response => {
                elements.buttons.reset.disabled = false;
                elements.buttons.reset.textContent = 'Enviar enlace';
                
                if (response.success) {
                    utils.showMessage(response.data.message, 'success');
                } else {
                    utils.showMessage(response.data.message, 'error');
                }
            })
            .catch(error => {
                elements.buttons.reset.disabled = false;
                elements.buttons.reset.textContent = 'Enviar enlace';
                utils.showMessage('Error de conexión', 'error');
                console.error('Error:', error);
            });
    };

    /* =====================
       EVENT LISTENERS
       ===================== */
    
    // Cerrar modal
    elements.close.addEventListener('click', modalManager.close);
    elements.overlay.addEventListener('click', modalManager.close);
    
    // Cerrar con tecla ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            modalManager.close();
        }
    });
    
    // Trigger externo: abrir modal
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.mu-open-modal, .mu-open-auth-modal');
        if (trigger) {
            e.preventDefault();
            modalManager.open();
        }
    });
    
    // Botón Continuar (Paso 1)
    elements.buttons.continue.addEventListener('click', handleContinue);
    elements.inputs.user.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleContinue();
        }
    });
    
    // Navegación: Volver al paso 1
    elements.buttons.backToStep1.addEventListener('click', modalManager.reset);
    elements.buttons.backToStep1Reg.addEventListener('click', modalManager.reset);
    
    // Link "Olvidaste tu contraseña"
    elements.ui.forgotLink.addEventListener('click', (e) => {
        e.preventDefault();
        elements.steps.login.style.display = 'none';
        elements.ui.social.style.display = 'none';
        elements.steps.forgot.style.display = 'block';
        elements.ui.title.textContent = 'Recuperar Cuenta';
        elements.ui.subtitle.textContent = '';
        elements.inputs.forgotEmail.value = state.user;
    });
    
    // Volver desde "Recuperar contraseña" a Login
    elements.buttons.backToLogin.addEventListener('click', () => {
        elements.steps.forgot.style.display = 'none';
        elements.steps.login.style.display = 'block';
        elements.ui.social.style.display = 'block';
        elements.ui.title.textContent = '¡Te damos la bienvenida!';
        elements.ui.subtitle.textContent = 'Ingresa a tu cuenta o creá una nueva';
    });
    
    // Submit del formulario (Login o Registro)
    elements.form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Determinar qué paso está visible
        if (elements.steps.login.style.display === 'block') {
            handleLogin();
        } else if (elements.steps.register.style.display === 'block') {
            handleRegister();
        }
    });
    
    // Botón "Enviar enlace" (Recuperar contraseña)
    elements.buttons.reset.addEventListener('click', handleResetPassword);

})();