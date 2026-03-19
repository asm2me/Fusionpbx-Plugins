/* ============================================
   VOIP@ Cloud Admin Panel - JavaScript
   ============================================ */

(function() {
    'use strict';

    // ---- SHA-256 utility (Web Crypto API) ----
    async function sha256(message) {
        const msgBuffer = new TextEncoder().encode(message);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    // ---- Default password hash (voipat2024) ----
    const DEFAULT_PASSWORD_HASH = 'f7a59e357ca5ec9a6e1ef071a7407bdfef8cf78b05d7887d8d1e9f1fb02bcaf1';

    // ---- Content Manager ----
    const ContentManager = {
        _data: null,
        _defaultData: null,

        async loadDefaults() {
            try {
                const resp = await fetch('content.json');
                this._defaultData = await resp.json();
            } catch (e) {
                console.error('Failed to load content.json:', e);
                this._defaultData = { blogPosts: [], pricing: [], contact: {}, seo: {}, settings: {} };
            }
        },

        getData() {
            if (this._data) return this._data;
            const stored = localStorage.getItem('voipat_admin_content');
            if (stored) {
                try {
                    this._data = JSON.parse(stored);
                } catch (e) {
                    this._data = null;
                }
            }
            if (!this._data) {
                this._data = JSON.parse(JSON.stringify(this._defaultData));
            }
            return this._data;
        },

        save() {
            localStorage.setItem('voipat_admin_content', JSON.stringify(this._data));
        },

        getPasswordHash() {
            const data = this.getData();
            return (data.settings && data.settings.adminPasswordHash) || DEFAULT_PASSWORD_HASH;
        },

        setPasswordHash(hash) {
            const data = this.getData();
            if (!data.settings) data.settings = {};
            data.settings.adminPasswordHash = hash;
            this.save();
        },

        exportJSON() {
            const dataStr = JSON.stringify(this.getData(), null, 2);
            const blob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'voipat-content-' + new Date().toISOString().slice(0, 10) + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },

        importJSON(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        const imported = JSON.parse(e.target.result);
                        this._data = imported;
                        this.save();
                        resolve(imported);
                    } catch (err) {
                        reject(new Error('Invalid JSON file'));
                    }
                };
                reader.onerror = () => reject(new Error('Failed to read file'));
                reader.readAsText(file);
            });
        },

        resetToDefaults() {
            this._data = JSON.parse(JSON.stringify(this._defaultData));
            this.save();
        }
    };

    // ---- Toast Notifications ----
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = '<i class="fas ' + icons[type] + '"></i><span>' + message + '</span><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>';
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // ---- Confirm Dialog ----
    function showConfirm(title, message) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'confirm-overlay';
            overlay.innerHTML =
                '<div class="confirm-dialog">' +
                    '<div class="confirm-icon"><i class="fas fa-exclamation-triangle"></i></div>' +
                    '<h3>' + title + '</h3>' +
                    '<p>' + message + '</p>' +
                    '<div class="confirm-actions">' +
                        '<button class="btn-admin btn-admin-ghost" id="confirmCancel">Cancel</button>' +
                        '<button class="btn-admin btn-admin-danger" id="confirmOk">Delete</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(overlay);
            overlay.querySelector('#confirmCancel').onclick = () => { overlay.remove(); resolve(false); };
            overlay.querySelector('#confirmOk').onclick = () => { overlay.remove(); resolve(true); };
            overlay.addEventListener('click', (e) => { if (e.target === overlay) { overlay.remove(); resolve(false); } });
        });
    }

    // ---- Markdown-like to HTML ----
    function markdownToHtml(text) {
        if (!text) return '';
        let html = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        // Headers
        html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
        html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
        // Bold
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Italic
        html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
        // Inline code
        html = html.replace(/`(.+?)`/g, '<code>$1</code>');
        // Unordered lists
        html = html.replace(/^- (.+)$/gm, '<li>$1</li>');
        html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');
        // Numbered lists
        html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
        // Paragraphs
        html = html.replace(/\n\n/g, '</p><p>');
        html = '<p>' + html + '</p>';
        html = html.replace(/<p><h([123])>/g, '<h$1>');
        html = html.replace(/<\/h([123])><\/p>/g, '</h$1>');
        html = html.replace(/<p><ul>/g, '<ul>');
        html = html.replace(/<\/ul><\/p>/g, '</ul>');
        html = html.replace(/<p>\s*<\/p>/g, '');
        return html;
    }

    // ---- Generate unique ID ----
    function generateId() {
        return 'post-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
    }

    // ---- App State ----
    let currentSection = 'dashboard';
    let editingPostId = null;
    let isAuthenticated = false;

    // ---- DOM Ready ----
    document.addEventListener('DOMContentLoaded', async () => {
        await ContentManager.loadDefaults();
        ContentManager.getData();
        initLogin();
        initSidebar();
        initSections();
    });

    // ---- Login ----
    function initLogin() {
        const loginOverlay = document.getElementById('loginOverlay');
        const loginForm = document.getElementById('loginForm');
        const loginError = document.getElementById('loginError');
        const layout = document.getElementById('adminLayout');

        // Check session
        const session = sessionStorage.getItem('voipat_admin_session');
        if (session === 'authenticated') {
            isAuthenticated = true;
            loginOverlay.classList.add('hidden');
            layout.classList.remove('locked');
            renderCurrentSection();
            return;
        }

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = document.getElementById('loginPassword').value;
            const hash = await sha256(password);
            const storedHash = ContentManager.getPasswordHash();

            if (hash === storedHash) {
                isAuthenticated = true;
                sessionStorage.setItem('voipat_admin_session', 'authenticated');
                loginOverlay.classList.add('hidden');
                layout.classList.remove('locked');
                renderCurrentSection();
                showToast('Welcome to the admin panel!', 'success');
            } else {
                loginError.classList.add('visible');
                document.getElementById('loginPassword').value = '';
                setTimeout(() => loginError.classList.remove('visible'), 3000);
            }
        });
    }

    // ---- Sidebar ----
    function initSidebar() {
        const links = document.querySelectorAll('.sidebar-link[data-section]');
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = link.dataset.section;
                switchSection(section);
                // Close sidebar on mobile
                document.getElementById('adminSidebar').classList.remove('open');
                const overlay = document.getElementById('sidebarOverlay');
                if (overlay) overlay.classList.remove('visible');
            });
        });

        // Hamburger
        const hamburger = document.getElementById('hamburgerAdmin');
        if (hamburger) {
            hamburger.addEventListener('click', () => {
                document.getElementById('adminSidebar').classList.toggle('open');
                const overlay = document.getElementById('sidebarOverlay');
                if (overlay) overlay.classList.toggle('visible');
            });
        }

        // Sidebar overlay
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) {
            overlay.addEventListener('click', () => {
                document.getElementById('adminSidebar').classList.remove('open');
                overlay.classList.remove('visible');
            });
        }

        // Logout
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                sessionStorage.removeItem('voipat_admin_session');
                isAuthenticated = false;
                document.getElementById('loginOverlay').classList.remove('hidden');
                document.getElementById('adminLayout').classList.add('locked');
            });
        }

        // Back to site
        const backBtn = document.getElementById('backToSite');
        if (backBtn) {
            backBtn.addEventListener('click', (e) => {
                e.preventDefault();
                window.location.href = 'index.html';
            });
        }
    }

    function switchSection(section) {
        currentSection = section;
        // Update sidebar active
        document.querySelectorAll('.sidebar-link[data-section]').forEach(l => l.classList.remove('active'));
        const activeLink = document.querySelector('.sidebar-link[data-section="' + section + '"]');
        if (activeLink) activeLink.classList.add('active');
        // Update topbar title
        const titles = {
            dashboard: 'Dashboard',
            pages: 'Pages',
            blog: 'Blog Posts',
            pricing: 'Plans & Pricing',
            contact: 'Contact Info',
            seo: 'SEO Settings',
            settings: 'Settings'
        };
        document.getElementById('topbarTitle').textContent = titles[section] || 'Dashboard';
        // Show section
        document.querySelectorAll('.section-panel').forEach(p => p.classList.remove('active'));
        const panel = document.getElementById('section-' + section);
        if (panel) panel.classList.add('active');
        renderCurrentSection();
    }

    function renderCurrentSection() {
        switch (currentSection) {
            case 'dashboard': renderDashboard(); break;
            case 'blog': renderBlogList(); break;
            case 'pricing': renderPricing(); break;
            case 'contact': renderContact(); break;
            case 'seo': renderSEO(); break;
            case 'settings': renderSettings(); break;
        }
    }

    function initSections() {
        switchSection('dashboard');
    }

    // ---- Dashboard ----
    function renderDashboard() {
        const data = ContentManager.getData();
        const posts = data.blogPosts || [];
        const plans = data.pricing || [];
        document.getElementById('statBlogPosts').textContent = posts.length;
        document.getElementById('statPublished').textContent = posts.filter(p => p.published).length;
        document.getElementById('statPlans').textContent = plans.length;
        document.getElementById('statPages').textContent = '5';

        // Recent posts
        const tbody = document.getElementById('recentPostsBody');
        tbody.innerHTML = '';
        posts.slice(0, 5).forEach(post => {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><strong>' + escapeHtml(post.title) + '</strong></td>' +
                '<td>' + escapeHtml(post.author) + '</td>' +
                '<td>' + post.date + '</td>' +
                '<td><span class="tag ' + (post.published ? 'tag-published' : 'tag-draft') + '">' +
                    (post.published ? 'Published' : 'Draft') + '</span></td>';
            tbody.appendChild(tr);
        });

        // Import/export
        document.getElementById('exportBtn').onclick = () => {
            ContentManager.exportJSON();
            showToast('Content exported successfully!', 'success');
        };
        document.getElementById('importFile').onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            try {
                await ContentManager.importJSON(file);
                showToast('Content imported successfully!', 'success');
                renderCurrentSection();
            } catch (err) {
                showToast(err.message, 'error');
            }
            e.target.value = '';
        };
        document.getElementById('publishBtn').onclick = showPublishModal;
    }

    // ---- Blog Posts ----
    function renderBlogList() {
        const data = ContentManager.getData();
        const posts = data.blogPosts || [];
        const container = document.getElementById('blogListContainer');
        const editor = document.getElementById('blogEditorContainer');

        container.style.display = 'block';
        editor.style.display = 'none';

        const tbody = document.getElementById('blogTableBody');
        tbody.innerHTML = '';

        if (posts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--admin-text-muted);">No blog posts yet. Click "New Post" to create one.</td></tr>';
            return;
        }

        posts.forEach((post, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><strong>' + escapeHtml(post.title) + '</strong></td>' +
                '<td>' + escapeHtml(post.author) + '</td>' +
                '<td>' + post.date + '</td>' +
                '<td><span class="tag ' + (post.published ? 'tag-published' : 'tag-draft') + '">' +
                    (post.published ? 'Published' : 'Draft') + '</span></td>' +
                '<td>' +
                    '<div class="btn-group">' +
                        '<button class="btn-admin btn-admin-sm btn-admin-outline edit-post-btn" data-id="' + post.id + '"><i class="fas fa-edit"></i></button>' +
                        '<button class="btn-admin btn-admin-sm btn-admin-danger delete-post-btn" data-id="' + post.id + '"><i class="fas fa-trash"></i></button>' +
                    '</div>' +
                '</td>';
            tbody.appendChild(tr);
        });

        // Attach events
        tbody.querySelectorAll('.edit-post-btn').forEach(btn => {
            btn.onclick = () => openPostEditor(btn.dataset.id);
        });
        tbody.querySelectorAll('.delete-post-btn').forEach(btn => {
            btn.onclick = () => deletePost(btn.dataset.id);
        });

        // New post button
        document.getElementById('newPostBtn').onclick = () => openPostEditor(null);
    }

    function openPostEditor(postId) {
        const data = ContentManager.getData();
        const container = document.getElementById('blogListContainer');
        const editor = document.getElementById('blogEditorContainer');
        container.style.display = 'none';
        editor.style.display = 'block';

        editingPostId = postId;
        let post = {
            id: '',
            title: '',
            date: new Date().toISOString().slice(0, 10),
            excerpt: '',
            author: '',
            content: '',
            tags: [],
            color: 'purple',
            icon: 'fa-file-alt',
            published: false
        };

        if (postId) {
            const found = (data.blogPosts || []).find(p => p.id === postId);
            if (found) post = JSON.parse(JSON.stringify(found));
        }

        document.getElementById('postTitle').value = post.title;
        document.getElementById('postDate').value = post.date;
        document.getElementById('postAuthor').value = post.author;
        document.getElementById('postExcerpt').value = post.excerpt;
        document.getElementById('postContent').value = post.content;
        document.getElementById('postIcon').value = post.icon || 'fa-file-alt';
        document.getElementById('postPublished').checked = post.published;

        // Tags
        renderTagInput(post.tags || []);

        // Color
        document.querySelectorAll('.color-option').forEach(opt => {
            opt.classList.toggle('selected', opt.dataset.color === post.color);
        });

        // Preview
        updatePreview();

        // Color selection
        document.querySelectorAll('.color-option').forEach(opt => {
            opt.onclick = () => {
                document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
            };
        });

        // Live preview on content change
        document.getElementById('postContent').oninput = updatePreview;
        document.getElementById('postTitle').oninput = updatePreview;

        // Save
        document.getElementById('savePostBtn').onclick = savePost;
        document.getElementById('cancelPostBtn').onclick = () => renderBlogList();

        // Editor toolbar
        document.querySelectorAll('.editor-btn').forEach(btn => {
            btn.onclick = () => {
                const action = btn.dataset.action;
                const textarea = document.getElementById('postContent');
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const selected = textarea.value.substring(start, end);
                let replacement = '';

                switch (action) {
                    case 'bold': replacement = '**' + (selected || 'bold text') + '**'; break;
                    case 'italic': replacement = '*' + (selected || 'italic text') + '*'; break;
                    case 'h2': replacement = '\n## ' + (selected || 'Heading') + '\n'; break;
                    case 'h3': replacement = '\n### ' + (selected || 'Subheading') + '\n'; break;
                    case 'list': replacement = '\n- ' + (selected || 'List item') + '\n'; break;
                    case 'code': replacement = '`' + (selected || 'code') + '`'; break;
                }

                textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
                textarea.focus();
                updatePreview();
            };
        });
    }

    function updatePreview() {
        const content = document.getElementById('postContent').value;
        const title = document.getElementById('postTitle').value;
        const preview = document.getElementById('postPreview');
        preview.innerHTML = (title ? '<h1>' + escapeHtml(title) + '</h1>' : '') + markdownToHtml(content);
    }

    function renderTagInput(tags) {
        const container = document.getElementById('tagInputContainer');
        container.innerHTML = '';
        tags.forEach(tag => {
            const pill = document.createElement('span');
            pill.className = 'tag-pill';
            pill.innerHTML = escapeHtml(tag) + ' <span class="remove-tag">&times;</span>';
            pill.querySelector('.remove-tag').onclick = () => { pill.remove(); };
            container.appendChild(pill);
        });
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'tag-input';
        input.placeholder = 'Add tag...';
        input.onkeydown = (e) => {
            if ((e.key === 'Enter' || e.key === ',') && input.value.trim()) {
                e.preventDefault();
                const pill = document.createElement('span');
                pill.className = 'tag-pill';
                pill.innerHTML = escapeHtml(input.value.trim()) + ' <span class="remove-tag">&times;</span>';
                pill.querySelector('.remove-tag').onclick = () => { pill.remove(); };
                container.insertBefore(pill, input);
                input.value = '';
            }
        };
        container.appendChild(input);
        container.onclick = () => input.focus();
    }

    function getTagsFromInput() {
        const pills = document.querySelectorAll('#tagInputContainer .tag-pill');
        return Array.from(pills).map(p => p.textContent.trim().replace(/\s*\u00d7\s*$/, '').replace(/\s*\u2715\s*$/, '').trim());
    }

    function savePost() {
        const data = ContentManager.getData();
        if (!data.blogPosts) data.blogPosts = [];

        const title = document.getElementById('postTitle').value.trim();
        const date = document.getElementById('postDate').value;
        const author = document.getElementById('postAuthor').value.trim();
        const excerpt = document.getElementById('postExcerpt').value.trim();
        const content = document.getElementById('postContent').value;
        const icon = document.getElementById('postIcon').value.trim();
        const published = document.getElementById('postPublished').checked;
        const color = document.querySelector('.color-option.selected')?.dataset.color || 'purple';
        const tags = getTagsFromInput();

        if (!title) { showToast('Title is required', 'error'); return; }
        if (!author) { showToast('Author is required', 'error'); return; }

        const post = {
            id: editingPostId || generateId(),
            title, date, excerpt, author, content, tags, color, icon, published
        };

        if (editingPostId) {
            const idx = data.blogPosts.findIndex(p => p.id === editingPostId);
            if (idx >= 0) data.blogPosts[idx] = post;
        } else {
            data.blogPosts.unshift(post);
        }

        ContentManager.save();
        showToast(editingPostId ? 'Post updated!' : 'Post created!', 'success');
        editingPostId = null;
        renderBlogList();
    }

    async function deletePost(postId) {
        const confirmed = await showConfirm('Delete Post', 'Are you sure you want to delete this blog post? This action cannot be undone.');
        if (!confirmed) return;

        const data = ContentManager.getData();
        data.blogPosts = (data.blogPosts || []).filter(p => p.id !== postId);
        ContentManager.save();
        showToast('Post deleted.', 'success');
        renderBlogList();
    }

    // ---- Pricing ----
    function renderPricing() {
        const data = ContentManager.getData();
        const plans = data.pricing || [];
        const container = document.getElementById('pricingContainer');
        container.innerHTML = '';

        plans.forEach((plan, index) => {
            const card = document.createElement('div');
            card.className = 'admin-card';
            card.innerHTML =
                '<div class="admin-card-header">' +
                    '<h3><i class="fas fa-tag"></i> ' + escapeHtml(plan.name) + (plan.featured ? ' <span class="tag tag-published">Featured</span>' : '') + '</h3>' +
                    '<div class="btn-group">' +
                        '<button class="btn-admin btn-admin-sm btn-admin-danger delete-plan-btn" data-index="' + index + '"><i class="fas fa-trash"></i></button>' +
                    '</div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>Plan Name</label><input type="text" class="plan-name" value="' + escapeHtml(plan.name) + '"></div>' +
                    '<div class="form-group"><label>Description</label><input type="text" class="plan-desc" value="' + escapeHtml(plan.description) + '"></div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>Monthly Price ($)</label><input type="number" class="plan-monthly" value="' + plan.monthlyPrice + '"></div>' +
                    '<div class="form-group"><label>Yearly Price ($)</label><input type="number" class="plan-yearly" value="' + plan.yearlyPrice + '"></div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>CTA Text</label><input type="text" class="plan-cta-text" value="' + escapeHtml(plan.ctaText) + '"></div>' +
                    '<div class="form-group"><label>CTA Link</label><input type="text" class="plan-cta-link" value="' + escapeHtml(plan.ctaLink) + '"></div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label><input type="checkbox" class="plan-featured" ' + (plan.featured ? 'checked' : '') + '> Featured Plan (highlighted)</label>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Features</label>' +
                    '<div class="feature-list-editor" data-plan-index="' + index + '"></div>' +
                '</div>';
            container.appendChild(card);

            // Render features
            const featureEditor = card.querySelector('.feature-list-editor');
            renderFeatureList(featureEditor, plan.features || [], index);

            // Delete
            card.querySelector('.delete-plan-btn').onclick = () => deletePlan(index);
        });

        // Save button
        document.getElementById('savePricingBtn').onclick = savePricing;
        document.getElementById('addPlanBtn').onclick = addPlan;
    }

    function renderFeatureList(container, features, planIndex) {
        container.innerHTML = '';
        features.forEach((feature, fi) => {
            const row = document.createElement('div');
            row.className = 'feature-item-row';
            row.innerHTML =
                '<input type="text" value="' + feature.replace(/"/g, '&quot;') + '" class="feature-input">' +
                '<button class="remove-feature" title="Remove"><i class="fas fa-times"></i></button>';
            row.querySelector('.remove-feature').onclick = () => row.remove();
            container.appendChild(row);
        });
        const addBtn = document.createElement('button');
        addBtn.className = 'add-feature-btn';
        addBtn.innerHTML = '<i class="fas fa-plus"></i> Add Feature';
        addBtn.onclick = () => {
            const row = document.createElement('div');
            row.className = 'feature-item-row';
            row.innerHTML =
                '<input type="text" value="" placeholder="Feature description" class="feature-input">' +
                '<button class="remove-feature" title="Remove"><i class="fas fa-times"></i></button>';
            row.querySelector('.remove-feature').onclick = () => row.remove();
            container.insertBefore(row, addBtn);
        };
        container.appendChild(addBtn);
    }

    function savePricing() {
        const data = ContentManager.getData();
        const cards = document.querySelectorAll('#pricingContainer .admin-card');
        const plans = [];

        cards.forEach((card, i) => {
            const featureInputs = card.querySelectorAll('.feature-input');
            const features = Array.from(featureInputs).map(f => f.value).filter(f => f.trim());

            plans.push({
                id: (data.pricing[i] && data.pricing[i].id) || 'plan-' + i,
                name: card.querySelector('.plan-name').value.trim(),
                description: card.querySelector('.plan-desc').value.trim(),
                monthlyPrice: parseFloat(card.querySelector('.plan-monthly').value) || 0,
                yearlyPrice: parseFloat(card.querySelector('.plan-yearly').value) || 0,
                featured: card.querySelector('.plan-featured').checked,
                features: features,
                ctaText: card.querySelector('.plan-cta-text').value.trim(),
                ctaLink: card.querySelector('.plan-cta-link').value.trim()
            });
        });

        data.pricing = plans;
        ContentManager.save();
        showToast('Pricing plans saved!', 'success');
    }

    function addPlan() {
        const data = ContentManager.getData();
        if (!data.pricing) data.pricing = [];
        data.pricing.push({
            id: 'plan-' + Date.now(),
            name: 'New Plan',
            description: 'Plan description',
            monthlyPrice: 0,
            yearlyPrice: 0,
            featured: false,
            features: ['Feature 1'],
            ctaText: 'Get Started',
            ctaLink: 'register.html'
        });
        ContentManager.save();
        renderPricing();
        showToast('New plan added!', 'info');
    }

    async function deletePlan(index) {
        const confirmed = await showConfirm('Delete Plan', 'Are you sure you want to delete this pricing plan?');
        if (!confirmed) return;
        const data = ContentManager.getData();
        data.pricing.splice(index, 1);
        ContentManager.save();
        renderPricing();
        showToast('Plan deleted.', 'success');
    }

    // ---- Contact ----
    function renderContact() {
        const data = ContentManager.getData();
        const contact = data.contact || {};
        const social = contact.social || {};

        document.getElementById('contactEmail').value = contact.email || '';
        document.getElementById('contactPhone').value = contact.phone || '';
        document.getElementById('contactLiveChat').value = contact.liveChat || '';
        document.getElementById('contactSupportHours').value = contact.supportHours || '';
        document.getElementById('socialTwitter').value = social.twitter || '';
        document.getElementById('socialLinkedin').value = social.linkedin || '';
        document.getElementById('socialFacebook').value = social.facebook || '';
        document.getElementById('socialYoutube').value = social.youtube || '';

        document.getElementById('saveContactBtn').onclick = saveContact;
    }

    function saveContact() {
        const data = ContentManager.getData();
        data.contact = {
            email: document.getElementById('contactEmail').value.trim(),
            phone: document.getElementById('contactPhone').value.trim(),
            liveChat: document.getElementById('contactLiveChat').value.trim(),
            supportHours: document.getElementById('contactSupportHours').value.trim(),
            social: {
                twitter: document.getElementById('socialTwitter').value.trim(),
                linkedin: document.getElementById('socialLinkedin').value.trim(),
                facebook: document.getElementById('socialFacebook').value.trim(),
                youtube: document.getElementById('socialYoutube').value.trim()
            }
        };
        ContentManager.save();
        showToast('Contact info saved!', 'success');
    }

    // ---- SEO ----
    function renderSEO() {
        const data = ContentManager.getData();
        const seo = data.seo || {};

        // Index page
        const indexSeo = seo.index || {};
        document.getElementById('seoIndexTitle').value = indexSeo.title || '';
        document.getElementById('seoIndexDesc').value = indexSeo.description || '';
        document.getElementById('seoIndexOgTitle').value = indexSeo.ogTitle || '';
        document.getElementById('seoIndexOgDesc').value = indexSeo.ogDescription || '';
        document.getElementById('seoIndexOgImage').value = indexSeo.ogImage || '';

        // Blog page
        const blogSeo = seo.blog || {};
        document.getElementById('seoBlogTitle').value = blogSeo.title || '';
        document.getElementById('seoBlogDesc').value = blogSeo.description || '';
        document.getElementById('seoBlogOgTitle').value = blogSeo.ogTitle || '';
        document.getElementById('seoBlogOgDesc').value = blogSeo.ogDescription || '';
        document.getElementById('seoBlogOgImage').value = blogSeo.ogImage || '';

        document.getElementById('saveSeoBtn').onclick = saveSEO;
    }

    function saveSEO() {
        const data = ContentManager.getData();
        data.seo = {
            index: {
                title: document.getElementById('seoIndexTitle').value.trim(),
                description: document.getElementById('seoIndexDesc').value.trim(),
                ogTitle: document.getElementById('seoIndexOgTitle').value.trim(),
                ogDescription: document.getElementById('seoIndexOgDesc').value.trim(),
                ogImage: document.getElementById('seoIndexOgImage').value.trim()
            },
            blog: {
                title: document.getElementById('seoBlogTitle').value.trim(),
                description: document.getElementById('seoBlogDesc').value.trim(),
                ogTitle: document.getElementById('seoBlogOgTitle').value.trim(),
                ogDescription: document.getElementById('seoBlogOgDesc').value.trim(),
                ogImage: document.getElementById('seoBlogOgImage').value.trim()
            }
        };
        ContentManager.save();
        showToast('SEO settings saved!', 'success');
    }

    // ---- Settings ----
    function renderSettings() {
        const data = ContentManager.getData();
        const settings = data.settings || {};

        document.getElementById('settingSiteName').value = settings.siteName || '';
        document.getElementById('settingServerIp').value = settings.serverIp || '';
        document.getElementById('settingSupportEmail').value = settings.supportEmail || '';

        document.getElementById('saveSettingsBtn').onclick = saveSettings;
        document.getElementById('changePasswordBtn').onclick = changePassword;
        document.getElementById('resetContentBtn').onclick = resetContent;
    }

    function saveSettings() {
        const data = ContentManager.getData();
        if (!data.settings) data.settings = {};
        data.settings.siteName = document.getElementById('settingSiteName').value.trim();
        data.settings.serverIp = document.getElementById('settingServerIp').value.trim();
        data.settings.supportEmail = document.getElementById('settingSupportEmail').value.trim();
        ContentManager.save();
        showToast('Settings saved!', 'success');
    }

    async function changePassword() {
        const current = document.getElementById('currentPassword').value;
        const newPass = document.getElementById('newPassword').value;
        const confirmPass = document.getElementById('confirmPassword').value;

        if (!current || !newPass) {
            showToast('Please fill in all password fields.', 'error');
            return;
        }

        if (newPass !== confirmPass) {
            showToast('New passwords do not match.', 'error');
            return;
        }

        if (newPass.length < 6) {
            showToast('Password must be at least 6 characters.', 'error');
            return;
        }

        const currentHash = await sha256(current);
        if (currentHash !== ContentManager.getPasswordHash()) {
            showToast('Current password is incorrect.', 'error');
            return;
        }

        const newHash = await sha256(newPass);
        ContentManager.setPasswordHash(newHash);
        showToast('Password changed successfully!', 'success');

        document.getElementById('currentPassword').value = '';
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmPassword').value = '';
    }

    async function resetContent() {
        const confirmed = await showConfirm('Reset Content', 'This will reset ALL content to defaults. Any changes you made will be lost. Continue?');
        if (!confirmed) return;
        ContentManager.resetToDefaults();
        showToast('Content reset to defaults.', 'success');
        renderCurrentSection();
    }

    // ---- Publish Modal ----
    function showPublishModal() {
        const modal = document.getElementById('publishModal');
        modal.classList.remove('hidden');
        document.getElementById('closePublishModal').onclick = () => modal.classList.add('hidden');
        modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });

        document.getElementById('copyExportInstructions').onclick = () => {
            const text = document.getElementById('publishInstructions').textContent;
            navigator.clipboard.writeText(text).then(() => showToast('Instructions copied!', 'info'));
        };

        document.getElementById('downloadForPublish').onclick = () => {
            ContentManager.exportJSON();
            showToast('Content file downloaded.', 'success');
        };
    }

    // ---- Utility ----
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})();
