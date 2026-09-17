import React from 'react';
import { Link } from 'react-router-dom';
import { ROUTES } from '../../constants/routes';
import './Landing.css';

const Landing = () => {
  return (
    <div className="landing-container">
      <nav className="landing-nav">
        <div className="logo">HRMS SaaS</div>
        <div className="nav-links">
          <Link to={ROUTES.LOGIN} className="btn-login">Login</Link>
          <Link to={ROUTES.REGISTER} className="btn-register">Start Free Trial</Link>
        </div>
      </nav>
      
      <header className="landing-header">
        <h1>Modern HR Management for Every Company</h1>
        <p>Manage your employees, payroll, and recruitment from one simple platform.</p>
        <Link to={ROUTES.REGISTER} className="cta-button">Get Started</Link>
      </header>

      <section className="features-section">
        <div className="feature-card">
          <h3>Centralized HR</h3>
          <p>Keep all your employee records in one secure place.</p>
        </div>
        <div className="feature-card">
          <h3>Payroll Management</h3>
          <p>Automate your payroll processing and payslip generation.</p>
        </div>
        <div className="feature-card">
          <h3>Advanced ATS</h3>
          <p>Track candidates through every stage of the recruitment process.</p>
        </div>
      </section>
    </div>
  );
};

export default Landing;
