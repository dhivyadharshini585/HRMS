import React from 'react';
import { Row, Col, Card } from 'react-bootstrap';
import { Users, UserPlus, Calendar, AlertCircle } from 'lucide-react';

const AdminDashboard = () => {
  return (
    <div>
      <h3 className="mb-4">Admin Dashboard</h3>
      
      <Row className="mb-4">
        <Col md={3} sm={6} className="mb-3">
          <Card className="border-0 shadow-sm h-100">
            <Card.Body className="d-flex align-items-center">
              <div className="bg-primary bg-opacity-10 p-3 rounded text-primary me-3">
                <Users size={24} />
              </div>
              <div>
                <h6 className="text-muted mb-1">Total Employees</h6>
                <h3 className="mb-0">248</h3>
              </div>
            </Card.Body>
          </Card>
        </Col>
        
        <Col md={3} sm={6} className="mb-3">
          <Card className="border-0 shadow-sm h-100">
            <Card.Body className="d-flex align-items-center">
              <div className="bg-success bg-opacity-10 p-3 rounded text-success me-3">
                <UserPlus size={24} />
              </div>
              <div>
                <h6 className="text-muted mb-1">New Employees</h6>
                <h3 className="mb-0">12</h3>
              </div>
            </Card.Body>
          </Card>
        </Col>
        
        <Col md={3} sm={6} className="mb-3">
          <Card className="border-0 shadow-sm h-100">
            <Card.Body className="d-flex align-items-center">
              <div className="bg-warning bg-opacity-10 p-3 rounded text-warning me-3">
                <Calendar size={24} />
              </div>
              <div>
                <h6 className="text-muted mb-1">On Leave</h6>
                <h3 className="mb-0">18</h3>
              </div>
            </Card.Body>
          </Card>
        </Col>
        
        <Col md={3} sm={6} className="mb-3">
          <Card className="border-0 shadow-sm h-100">
            <Card.Body className="d-flex align-items-center">
              <div className="bg-danger bg-opacity-10 p-3 rounded text-danger me-3">
                <AlertCircle size={24} />
              </div>
              <div>
                <h6 className="text-muted mb-1">Absent Today</h6>
                <h3 className="mb-0">7</h3>
              </div>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      <Row>
        <Col md={6}>
          <Card className="border-0 shadow-sm">
            <Card.Header className="bg-white">
              <h5 className="mb-0">Today's Attendance</h5>
            </Card.Header>
            <Card.Body>
              <div className="d-flex justify-content-between mb-2">
                <span>Present</span>
                <span className="text-success fw-bold">90%</span>
              </div>
              <div className="d-flex justify-content-between mb-2">
                <span>Absent</span>
                <span className="text-danger fw-bold">3%</span>
              </div>
              <div className="d-flex justify-content-between mb-2">
                <span>Leave</span>
                <span className="text-warning fw-bold">7%</span>
              </div>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </div>
  );
};

export default AdminDashboard;
