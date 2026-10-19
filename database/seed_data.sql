-- =====================================================================
-- Hospital Management System (HMS) - Demo Seed Data
-- =====================================================================
-- Run AFTER schema.sql (hms.sql combines both automatically).
--
-- All demo passwords are: Admin@123 / Doctor@123 / Patient@123
-- (hashed below using PHP password_hash() with the bcrypt/default algo,
-- generated in advance so this file works with a plain SQL import).
-- =====================================================================

USE hms_db;

-- ---------------------------------------------------------------------
-- Admin account
-- Email: admin@hms.com   Password: Admin@123
-- ---------------------------------------------------------------------
INSERT INTO users (name, email, password, role, phone, address, date_of_birth, status) VALUES
('System Administrator', 'admin@hms.com', '$2b$10$DPM.WGoBkcGM1BK8xvmTsugSV7JD2zZmjDPsSYCOBdSxgooFPPwc2', 'admin', '9999999999', 'Hospital HQ', '1985-01-01', 'active');

-- ---------------------------------------------------------------------
-- Doctors (password for all: Doctor@123)
-- ---------------------------------------------------------------------
INSERT INTO users (name, email, password, role, phone, address, date_of_birth, status) VALUES
('Ananya Sharma', 'ananya.sharma@hms.com', '$2b$10$yleCBNq0CswjZMXFs57HAuCk5NzYGKnyrGkkiVs9TkwX00ZJFEhgS', 'doctor', '9000000001', 'Cardiology Wing', '1980-04-12', 'active'),
('Rohan Verma', 'rohan.verma@hms.com', '$2b$10$yleCBNq0CswjZMXFs57HAuCk5NzYGKnyrGkkiVs9TkwX00ZJFEhgS', 'doctor', '9000000002', 'Neurology Wing', '1978-09-23', 'active'),
('Kavya Iyer', 'kavya.iyer@hms.com', '$2b$10$yleCBNq0CswjZMXFs57HAuCk5NzYGKnyrGkkiVs9TkwX00ZJFEhgS', 'doctor', '9000000003', 'Dermatology Wing', '1985-02-17', 'active'),
('Sameer Khan', 'sameer.khan@hms.com', '$2b$10$yleCBNq0CswjZMXFs57HAuCk5NzYGKnyrGkkiVs9TkwX00ZJFEhgS', 'doctor', '9000000004', 'General Medicine Wing', '1975-11-05', 'active'),
('Priya Nair', 'priya.nair@hms.com', '$2b$10$yleCBNq0CswjZMXFs57HAuCk5NzYGKnyrGkkiVs9TkwX00ZJFEhgS', 'doctor', '9000000005', 'Orthopedics Wing', '1982-06-30', 'active');

INSERT INTO doctors (user_id, specialization, qualification, consultation_fee, availability, status) VALUES
((SELECT id FROM users WHERE email='ananya.sharma@hms.com'), 'Cardiology', 'MD Cardiology', 800.00, 'Mon-Fri 09:00-17:00', 'active'),
((SELECT id FROM users WHERE email='rohan.verma@hms.com'), 'Neurology', 'MD Neurology', 900.00, 'Mon-Fri 10:00-18:00', 'active'),
((SELECT id FROM users WHERE email='kavya.iyer@hms.com'), 'Dermatology', 'MD Dermatology', 600.00, 'Tue-Sat 09:00-16:00', 'active'),
((SELECT id FROM users WHERE email='sameer.khan@hms.com'), 'General Medicine', 'MBBS, MD', 400.00, 'Mon-Sat 08:00-14:00', 'active'),
((SELECT id FROM users WHERE email='priya.nair@hms.com'), 'Orthopedics', 'MS Orthopedics', 700.00, 'Mon-Fri 11:00-19:00', 'active');

-- ---------------------------------------------------------------------
-- Patients (password for all: Patient@123)
-- ---------------------------------------------------------------------
INSERT INTO users (name, email, password, role, phone, address, date_of_birth, status) VALUES
('Aarav Mehta', 'aarav.mehta@example.com', '$2b$10$E3EuAJgM/YvIqviIgRNgIO4RKRX4BSY9q6ctF3QgIT6vXr.Jh7m.K', 'patient', '9111111111', '12 MG Road, Pune', '1995-03-10', 'active'),
('Diya Kapoor', 'diya.kapoor@example.com', '$2b$10$E3EuAJgM/YvIqviIgRNgIO4RKRX4BSY9q6ctF3QgIT6vXr.Jh7m.K', 'patient', '9111111112', '45 Park Street, Pune', '1998-07-22', 'active'),
('Vivaan Joshi', 'vivaan.joshi@example.com', '$2b$10$E3EuAJgM/YvIqviIgRNgIO4RKRX4BSY9q6ctF3QgIT6vXr.Jh7m.K', 'patient', '9111111113', '78 Lake View, Pune', '1990-12-01', 'active'),
('Ishita Rao', 'ishita.rao@example.com', '$2b$10$E3EuAJgM/YvIqviIgRNgIO4RKRX4BSY9q6ctF3QgIT6vXr.Jh7m.K', 'patient', '9111111114', '5 Hill Road, Pune', '2000-05-18', 'active'),
('Kabir Malhotra', 'kabir.malhotra@example.com', '$2b$10$E3EuAJgM/YvIqviIgRNgIO4RKRX4BSY9q6ctF3QgIT6vXr.Jh7m.K', 'patient', '9111111115', '9 Church Street, Pune', '1988-09-09', 'active');

INSERT INTO patients (user_id, patient_id, emergency_contact, blood_group) VALUES
((SELECT id FROM users WHERE email='aarav.mehta@example.com'), 'PT-000001', '9222222221', 'O+'),
((SELECT id FROM users WHERE email='diya.kapoor@example.com'), 'PT-000002', '9222222222', 'A+'),
((SELECT id FROM users WHERE email='vivaan.joshi@example.com'), 'PT-000003', '9222222223', 'B+'),
((SELECT id FROM users WHERE email='ishita.rao@example.com'), 'PT-000004', '9222222224', 'AB+'),
((SELECT id FROM users WHERE email='kabir.malhotra@example.com'), 'PT-000005', '9222222225', 'O-');

-- ---------------------------------------------------------------------
-- Sample appointments (mix of future/past, various statuses)
-- ---------------------------------------------------------------------
INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, reason) VALUES
((SELECT id FROM patients WHERE patient_id='PT-000001'), (SELECT id FROM doctors WHERE specialization='Cardiology'), DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:00:00', 'Scheduled', 'Chest discomfort follow-up'),
((SELECT id FROM patients WHERE patient_id='PT-000002'), (SELECT id FROM doctors WHERE specialization='Dermatology'), DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:30:00', 'Confirmed', 'Skin rash'),
((SELECT id FROM patients WHERE patient_id='PT-000003'), (SELECT id FROM doctors WHERE specialization='General Medicine'), CURDATE(), '09:00:00', 'Scheduled', 'General checkup'),
((SELECT id FROM patients WHERE patient_id='PT-000004'), (SELECT id FROM doctors WHERE specialization='Orthopedics'), DATE_SUB(CURDATE(), INTERVAL 5 DAY), '14:00:00', 'Completed', 'Knee pain'),
((SELECT id FROM patients WHERE patient_id='PT-000005'), (SELECT id FROM doctors WHERE specialization='Neurology'), DATE_SUB(CURDATE(), INTERVAL 10 DAY), '15:00:00', 'Completed', 'Migraine evaluation');

-- ---------------------------------------------------------------------
-- Sample medical record + prescription for a completed appointment
-- ---------------------------------------------------------------------
INSERT INTO medical_records (patient_id, doctor_id, appointment_id, diagnosis, notes) VALUES
((SELECT id FROM patients WHERE patient_id='PT-000004'),
 (SELECT id FROM doctors WHERE specialization='Orthopedics'),
 (SELECT id FROM appointments WHERE patient_id=(SELECT id FROM patients WHERE patient_id='PT-000004') LIMIT 1),
 'Mild patellar tendinitis', 'Recommended rest and physiotherapy for two weeks.');

INSERT INTO prescriptions (medical_record_id, medicine_name, dosage, frequency, duration, instructions) VALUES
((SELECT id FROM medical_records WHERE patient_id=(SELECT id FROM patients WHERE patient_id='PT-000004') LIMIT 1),
 'Ibuprofen', '400mg', 'Twice daily', '7 days', 'Take after food');

-- ---------------------------------------------------------------------
-- Sample bill for the completed appointment (Unpaid, so Test Payment can be demoed)
-- ---------------------------------------------------------------------
INSERT INTO bills (patient_id, appointment_id, consultation_fee, treatment_charges, total_amount, status) VALUES
((SELECT id FROM patients WHERE patient_id='PT-000004'),
 (SELECT id FROM appointments WHERE patient_id=(SELECT id FROM patients WHERE patient_id='PT-000004') LIMIT 1),
 700.00, 250.00, 950.00, 'Unpaid');

-- ---------------------------------------------------------------------
-- Staff
-- ---------------------------------------------------------------------
INSERT INTO staff (name, email, phone, designation, department, status) VALUES
('Meera Pillai', 'meera.pillai@hms.com', '9333333331', 'Head Nurse', 'Nursing', 'active'),
('Arjun Das', 'arjun.das@hms.com', '9333333332', 'Lab Technician', 'Pathology', 'active'),
('Sneha Reddy', 'sneha.reddy@hms.com', '9333333333', 'Receptionist', 'Front Office', 'active'),
('Farhan Ali', 'farhan.ali@hms.com', '9333333334', 'Pharmacist', 'Pharmacy', 'active');

-- ---------------------------------------------------------------------
-- Wards and beds
-- ---------------------------------------------------------------------
INSERT INTO wards (ward_name, ward_type, capacity, status) VALUES
('General Ward A', 'General', 10, 'active'),
('ICU', 'Intensive Care', 6, 'active'),
('Maternity Ward', 'Maternity', 8, 'active');

INSERT INTO beds (ward_id, bed_number, status) VALUES
((SELECT id FROM wards WHERE ward_name='General Ward A'), 'A-01', 'Available'),
((SELECT id FROM wards WHERE ward_name='General Ward A'), 'A-02', 'Available'),
((SELECT id FROM wards WHERE ward_name='General Ward A'), 'A-03', 'Available'),
((SELECT id FROM wards WHERE ward_name='ICU'), 'ICU-01', 'Available'),
((SELECT id FROM wards WHERE ward_name='ICU'), 'ICU-02', 'Available'),
((SELECT id FROM wards WHERE ward_name='Maternity Ward'), 'M-01', 'Available'),
((SELECT id FROM wards WHERE ward_name='Maternity Ward'), 'M-02', 'Available');

-- Occupy one bed as a demo
UPDATE beds SET status='Occupied', patient_id=(SELECT id FROM patients WHERE patient_id='PT-000004'), assigned_at=NOW()
WHERE bed_number='A-01';

-- ---------------------------------------------------------------------
-- Sample notifications
-- ---------------------------------------------------------------------
INSERT INTO notifications (user_id, title, message, type, is_read) VALUES
((SELECT id FROM users WHERE email='aarav.mehta@example.com'), 'Appointment Booked', 'Your appointment with Dr. Ananya Sharma is scheduled.', 'appointment', 0),
((SELECT id FROM users WHERE email='diya.kapoor@example.com'), 'Appointment Confirmed', 'Your appointment with Dr. Kavya Iyer has been confirmed.', 'appointment', 0);
