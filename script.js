// Scroll reveal animations
const revealElements = document.querySelectorAll('.scroll-reveal');

const revealOnScroll = () => {
    const windowHeight = window.innerHeight;
    
    revealElements.forEach(element => {
        const elementTop = element.getBoundingClientRect().top;
        const revealPoint = 100;
        
        if (elementTop < windowHeight - revealPoint) {
            element.classList.add('revealed');
        }
    });
};

window.addEventListener('scroll', revealOnScroll);
revealOnScroll(); // Initial check

// Mobile hamburger menu toggle
document.addEventListener('DOMContentLoaded', () => {
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('nav-links');

    if (hamburger && navLinks) {
        // Toggle neural canvas visibility
        const toggleNeuralCanvas = (show) => {
            const neuralCanvas = document.querySelector('.neural-canvas');
            if (neuralCanvas) {
                neuralCanvas.style.opacity = show ? '0.1' : '0.6';
                neuralCanvas.style.transition = 'opacity 0.3s ease';
            }
        };

        hamburger.addEventListener('click', (e) => {
            e.stopPropagation();
            hamburger.classList.toggle('active');
            navLinks.classList.toggle('active');
            const isExpanded = navLinks.classList.contains('active');
            hamburger.setAttribute('aria-expanded', isExpanded);
            toggleNeuralCanvas(isExpanded);
        });

        // Close menu when a link is clicked
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navLinks.classList.remove('active');
                hamburger.setAttribute('aria-expanded', 'false');
                toggleNeuralCanvas(false);
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
                hamburger.classList.remove('active');
                navLinks.classList.remove('active');
                hamburger.setAttribute('aria-expanded', 'false');
                toggleNeuralCanvas(false);
            }
        });
    }
});

// Smooth scrolling for navigation links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Contact form submission with FormSpree
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.querySelector('.contact-form');
    
    if (contactForm) {
        const button = contactForm.querySelector('.cta-button');
        const originalButtonText = button.textContent;
        
        // FormSpree form ID
        const formspreeURL = 'https://formspree.io/f/xlggnvwj';
        
        contactForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Show loading state
            button.textContent = 'Sending...';
            button.disabled = true;
            
            // Get form values
            const data = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                subject: document.getElementById('subject').value,
                service: document.getElementById('service').value,
                message: document.getElementById('message').value,
                _subject: document.getElementById('subject').value
            };
            
            try {
                const response = await fetch(formspreeURL, {
                    method: 'POST',
                    body: JSON.stringify(data),
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                
                const result = await response.json();
                
                // Reset button
                button.textContent = originalButtonText;
                button.disabled = false;
                
                if (response.ok) {
                    showPopup('Success!', 'Thank you for your message! I\'ll get back to you soon.', 'success');
                    contactForm.reset();
                } else {
                    showPopup('Error', result.error || 'There was a problem sending your message.', 'error');
                }
            } catch (error) {
                // Reset button
                button.textContent = originalButtonText;
                button.disabled = false;
                
                showPopup('Error', 'Network error. Please check your connection and try again.', 'error');
            }
        });
    }
});

// Popup function
function showPopup(title, message, type) {
    
    // Create popup elements
    const overlay = document.createElement('div');
    overlay.className = 'popup-overlay';
    
    const popup = document.createElement('div');
    popup.className = `popup popup-${type}`;
    
    const popupTitle = document.createElement('h3');
    popupTitle.textContent = title;
    
    const popupMessage = document.createElement('p');
    popupMessage.textContent = message;
    
    const closeButton = document.createElement('button');
    closeButton.textContent = 'Close';
    closeButton.className = 'popup-close';
    
    // Assemble popup
    popup.appendChild(popupTitle);
    popup.appendChild(popupMessage);
    popup.appendChild(closeButton);
    overlay.appendChild(popup);
    document.body.appendChild(overlay);
    
    // Show popup with animation
    setTimeout(() => {
        overlay.classList.add('active');
    }, 10);
    
    // Close popup function
    let popupClosed = false;
    const closePopup = () => {
        if (popupClosed) return; // Prevent multiple closes
        popupClosed = true;
        
        overlay.classList.remove('active');
        setTimeout(() => {
            if (overlay.parentNode) {
                document.body.removeChild(overlay);
            }
        }, 300);
    };
    
    // Close on button click
    closeButton.addEventListener('click', closePopup);
    
    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            closePopup();
        }
    });
    
    // Auto close after 5 seconds
    setTimeout(closePopup, 5000);
}

// Interactive Neural Network Mouse Follower
// Only define if not already defined (portfolio-neural.js may have it)
if (typeof createNeuralNetwork === 'undefined') {
var createNeuralNetwork = () => {
    // Create canvas for neural connections
    const canvas = document.createElement('canvas');
    canvas.classList.add('neural-canvas');
    document.body.appendChild(canvas);
    
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    
    // Resize canvas on window resize
    window.addEventListener('resize', () => {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    });
    
    // Neural nodes array
    const nodes = [];
    const maxNodes = 40;
    const connectionDistance = 150;
    const mouseInfluenceDistance = 200;
    
    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    
    // Node class
    class Node {
        constructor(x, y, isFixed = false) {
            this.x = x;
            this.y = y;
            this.vx = (Math.random() - 0.5) * 0.5;
            this.vy = (Math.random() - 0.5) * 0.5;
            this.radius = 3;
            this.isFixed = isFixed;
        }
        
        update() {
            if (!this.isFixed) {
                // Move node
                this.x += this.vx;
                this.y += this.vy;
                
                // Bounce off edges
                if (this.x < 0 || this.x > canvas.width) this.vx *= -1;
                if (this.y < 0 || this.y > canvas.height) this.vy *= -1;
                
                // Keep within bounds
                this.x = Math.max(0, Math.min(canvas.width, this.x));
                this.y = Math.max(0, Math.min(canvas.height, this.y));
                
                // Attract to mouse
                const dx = mouseX - this.x;
                const dy = mouseY - this.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                
                if (distance < mouseInfluenceDistance) {
                    const force = (1 - distance / mouseInfluenceDistance) * 0.3;
                    this.vx += (dx / distance) * force;
                    this.vy += (dy / distance) * force;
                }
                
                // Friction
                this.vx *= 0.95;
                this.vy *= 0.95;
            } else {
                // Mouse node
                this.x = mouseX;
                this.y = mouseY;
            }
        }
        
        draw() {
            ctx.fillStyle = 'rgba(162, 166, 128, 0.8)';
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.fill();
            
            // Glow effect
            ctx.shadowBlur = 10;
            ctx.shadowColor = 'rgba(162, 166, 128, 0.5)';
        }
    }
    
    // Initialize nodes
    for (let i = 0; i < maxNodes; i++) {
        nodes.push(new Node(
            Math.random() * canvas.width,
            Math.random() * canvas.height
        ));
    }
    
    // Add mouse node
    const mouseNode = new Node(mouseX, mouseY, true);
    nodes.push(mouseNode);
    
    // Track mouse
    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
    });
    
    // Draw connections
    const drawConnections = () => {
        for (let i = 0; i < nodes.length; i++) {
            for (let j = i + 1; j < nodes.length; j++) {
                const dx = nodes[i].x - nodes[j].x;
                const dy = nodes[i].y - nodes[j].y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                
                if (distance < connectionDistance) {
                    const opacity = (1 - distance / connectionDistance) * 0.5;
                    ctx.strokeStyle = `rgba(162, 166, 128, ${opacity})`;
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(nodes[i].x, nodes[i].y);
                    ctx.lineTo(nodes[j].x, nodes[j].y);
                    ctx.stroke();
                }
            }
        }
    };
    
    // Animation loop
    const animate = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Reset shadow
        ctx.shadowBlur = 0;
        
        // Draw connections first
        drawConnections();
        
        // Update and draw nodes
        nodes.forEach(node => {
            node.update();
            node.draw();
        });
        
        requestAnimationFrame(animate);
    };
    
    animate();
};
}

// Initialize neural network only if canvas doesn't exist yet
if (!document.querySelector('.neural-canvas')) {
    createNeuralNetwork();
}
