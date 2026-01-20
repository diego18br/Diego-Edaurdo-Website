// Interactive Neural Network Mouse Follower for Portfolio Pages
const createNeuralNetwork = () => {
    // Find the floating-elements container instead of appending to body
    const floatingContainer = document.querySelector('.floating-elements');
    
    if (!floatingContainer) {
        console.error('floating-elements container not found');
        return;
    }
    
    // Create canvas for neural connections
    const canvas = document.createElement('canvas');
    canvas.classList.add('neural-canvas');
    floatingContainer.appendChild(canvas); // Append to floating-elements instead of body
    
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

// Initialize neural network
createNeuralNetwork();
