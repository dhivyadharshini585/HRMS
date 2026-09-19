import '../../styles/auth.css';

export default function AuthLayout({ children }) {
  return (
    <div className="auth-page bg-surface font-body-md text-on-surface antialiased min-h-screen flex items-center justify-center p-space-md" style={{ backgroundColor: '#f5f7f9', color: '#131b2e', fontFamily: 'Inter, sans-serif', minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' }}>
      <main className="w-full mx-auto max-w-4xl" style={{ width: '100%', margin: '0 auto', maxWidth: '56rem' }}>
        <div className="flex flex-col w-full" style={{ display: 'flex', flexDirection: 'column', width: '100%' }}>
          
          <div className="w-full max-w-4xl mx-auto my-auto overflow-hidden rounded-xl shadow-xl bg-surface-container-lowest flex flex-col md:flex-row" style={{ width: '100%', maxWidth: '56rem', margin: 'auto', overflow: 'hidden', borderRadius: '1rem', boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)', backgroundColor: '#ffffff', display: 'flex', minHeight: '500px' }}>
            
            {/* Left Hero/Branding Showcase Panel */}
            <div className="relative w-full md:w-1/2 bg-primary-container text-on-primary flex flex-col items-center justify-center overflow-hidden p-space-md" style={{ position: 'relative', width: '50%', backgroundColor: '#0a4d3a', color: '#ffffff', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', overflow: 'hidden', padding: '2rem' }}>
              
              {/* Subtle Ambient Glow / Curves */}
              <div className="absolute -top-24 -left-24 w-80 h-80 rounded-full bg-secondary-container opacity-10 blur-3xl pointer-events-none" style={{ position: 'absolute', top: '-6rem', left: '-6rem', width: '20rem', height: '20rem', borderRadius: '9999px', backgroundColor: '#6cf8bb', opacity: 0.1, filter: 'blur(64px)', pointerEvents: 'none' }}></div>
              <div className="absolute -bottom-20 -right-20 w-72 h-72 rounded-full bg-primary opacity-30 blur-2xl pointer-events-none" style={{ position: 'absolute', bottom: '-5rem', right: '-5rem', width: '18rem', height: '18rem', borderRadius: '9999px', backgroundColor: '#003527', opacity: 0.3, filter: 'blur(40px)', pointerEvents: 'none' }}></div>
              <svg style={{position: 'absolute', top: 0, right: 0, opacity: 0.05, width: '100%', height: '100%', pointerEvents: 'none'}} viewBox="0 0 100 100" preserveAspectRatio="none">
                <path d="M0,0 Q50,100 100,0 L100,100 L0,100 Z" fill="#ffffff" />
              </svg>

              <div style={{ position: 'relative', zIndex: 10, display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center' }}>
                {/* Logo Box */}
                <div style={{ width: '80px', height: '80px', backgroundColor: '#348e6c', borderRadius: '16px', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: '1.5rem', boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1)' }}>
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                  </svg>
                </div>

                <h1 style={{ fontSize: '48px', fontWeight: 700, color: '#ffffff', letterSpacing: '2px', margin: 0, lineHeight: 1 }}>HRMS</h1>
                
                <div style={{ marginTop: '1rem', fontSize: '13px', fontWeight: 500, letterSpacing: '2px', color: '#a7d5c3', textTransform: 'uppercase', lineHeight: '1.4' }}>
                  HUMAN RESOURCE<br/>MANAGEMENT SYSTEM
                </div>

                <div style={{ width: '40px', height: '4px', backgroundColor: '#6cf8bb', borderRadius: '2px', margin: '2rem auto' }}></div>

                <div style={{ fontSize: '13px', color: '#a7d5c3', lineHeight: '1.6' }}>
                  People &bull; Processes &bull; Productivity<br/>for a Better Tomorrow
                </div>
              </div>

            </div>
            
            {/* Right Authentication Form Panel */}
            <div className="w-full md:w-1/2 flex flex-col justify-center" style={{ width: '50%', backgroundColor: '#ffffff', display: 'flex', flexDirection: 'column', justifyContent: 'center', padding: '3rem 2.5rem' }}>
              <div style={{ maxWidth: '380px', margin: '0 auto', width: '100%' }}>
                {children}
              </div>
            </div>
            
          </div>
        </div>
      </main>
    </div>
  );
}
