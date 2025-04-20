<style>
    :root {
        --primary-color: #4e73df;
        --secondary-color: #858796;
        --success-color: #1cc88a;
        --info-color: #36b9cc;
        --warning-color: #f6c23e;
        --danger-color: #e74a3b;
    }
    
    body {
        background-color: #f8f9fc;
        font-family: "Nunito", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    
    .sidebar {
        min-height: 100vh;
        background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
        color: white;
    }
    
    .sidebar .nav-link {
        color: rgba(255, 255, 255, 0.8);
        padding: 0.75rem 1rem;
    }
    
    .sidebar .nav-link:hover {
        color: #fff;
    }
    
    .sidebar .nav-link.active {
        font-weight: bold;
        color: #fff;
    }
    
    .sidebar-brand {
        height: 4.375rem;
        font-size: 1.2rem;
        font-weight: 800;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.05rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Add other common styles as needed */
</style>
