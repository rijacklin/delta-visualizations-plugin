// local/myplugin/amd/src/tabs.js

export const init = (containerId) => {
    const container = document.getElementById(containerId);

    if (!container) {
        return;
    }

    const tabs = container.querySelectorAll('[role="tab"]');
    const panels = container.querySelectorAll('[role="tabpanel"]');

    const activate = (tab) => {
        const target = tab.getAttribute('data-target');

        tabs.forEach(t => {
            t.setAttribute('aria-selected', 'false');
            t.classList.remove('active');
        });

        panels.forEach(panel => {
            panel.classList.remove('active', 'show');
            panel.hidden = true;
        });

        tab.setAttribute('aria-selected', 'true');
        tab.classList.add('active');

        const panel = document.getElementById(target);

        if (panel) {
            panel.hidden = false;
            panel.classList.add('active', 'show');
        }
    };

    tabs.forEach(tab => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            activate(tab);
        });
    });
};
