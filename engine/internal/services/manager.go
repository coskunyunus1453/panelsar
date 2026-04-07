package services

import (
	"fmt"
	"os/exec"
	"strings"
	"sync"

	"hostvim/engine/internal/config"
	"github.com/sirupsen/logrus"
)

type ServiceStatus string

const (
	StatusRunning  ServiceStatus = "running"
	StatusStopped  ServiceStatus = "stopped"
	StatusError    ServiceStatus = "error"
	StatusUnknown  ServiceStatus = "unknown"
)

type ServiceInfo struct {
	Name    string        `json:"name"`
	Status  ServiceStatus `json:"status"`
	Enabled bool          `json:"enabled"`
	PID     int           `json:"pid,omitempty"`
	Memory  uint64        `json:"memory,omitempty"`
	CPU     float64       `json:"cpu,omitempty"`
}

type Manager struct {
	cfg      *config.Config
	log      *logrus.Logger
	services map[string]*ServiceInfo
	mu       sync.RWMutex
}

func NewManager(cfg *config.Config, log *logrus.Logger) *Manager {
	return &Manager{
		cfg:      cfg,
		log:      log,
		services: make(map[string]*ServiceInfo),
	}
}

func (m *Manager) DiscoverServices() error {
	m.mu.Lock()
	defer m.mu.Unlock()

	serviceNames := []string{"nginx", "apache2", "php-fpm", "mysql", "postgresql", "redis-server", "postfix", "dovecot"}

	for _, name := range serviceNames {
		info := &ServiceInfo{
			Name:   name,
			Status: StatusUnknown,
		}
		info.Status = m.checkServiceStatus(name)
		m.services[name] = info
	}

	m.log.Infof("Discovered %d services", len(m.services))
	return nil
}

func (m *Manager) GetAllServices() []ServiceInfo {
	m.mu.Lock()
	defer m.mu.Unlock()

	result := make([]ServiceInfo, 0, len(m.services))
	for _, svc := range m.services {
		// Dashboard kısayollarında stale "error" görünmemesi için
		// her listelemede gerçek systemd durumunu tazele.
		svc.Status = m.checkServiceStatus(svc.Name)
		result = append(result, *svc)
	}
	return result
}

func (m *Manager) GetService(name string) (*ServiceInfo, error) {
	m.mu.Lock()
	defer m.mu.Unlock()

	svc, ok := m.services[name]
	if !ok {
		return nil, fmt.Errorf("service %s not found", name)
	}
	svc.Status = m.checkServiceStatus(svc.Name)
	return svc, nil
}

func (m *Manager) StartService(name string) error {
	return m.controlService(name, "start")
}

func (m *Manager) StopService(name string) error {
	return m.controlService(name, "stop")
}

func (m *Manager) RestartService(name string) error {
	return m.controlService(name, "restart")
}

func (m *Manager) controlService(name, action string) error {
	m.mu.Lock()
	defer m.mu.Unlock()

	svc, ok := m.services[name]
	if !ok {
		return fmt.Errorf("service %s not found", name)
	}

	m.log.Infof("Executing %s on service %s", action, name)

	cmd := exec.Command("systemctl", action, name)
	output, err := cmd.CombinedOutput()
	if err != nil {
		svc.Status = StatusError
		return fmt.Errorf("failed to %s %s: %s - %w", action, name, string(output), err)
	}

	svc.Status = m.checkServiceStatus(name)
	return nil
}

func (m *Manager) checkServiceStatus(name string) ServiceStatus {
	cmd := exec.Command("systemctl", "is-active", name)
	output, err := cmd.Output()
	if err != nil {
		return StatusStopped
	}

	status := strings.TrimSpace(string(output))
	switch status {
	case "active":
		return StatusRunning
	case "inactive":
		return StatusStopped
	default:
		return StatusUnknown
	}
}
