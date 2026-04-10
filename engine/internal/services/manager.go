package services

import (
	"fmt"
	"os/exec"
	"sort"
	"strings"
	"sync"

	"github.com/sirupsen/logrus"
	"hostvim/engine/internal/config"
)

type ServiceStatus string

const (
	StatusRunning ServiceStatus = "running"
	StatusStopped ServiceStatus = "stopped"
	StatusError   ServiceStatus = "error"
	StatusUnknown ServiceStatus = "unknown"
)

type ServiceInfo struct {
	Name    string        `json:"name"`
	Status  ServiceStatus `json:"status"`
	Enabled bool          `json:"enabled"`
	PID     int           `json:"pid,omitempty"`
	Memory  uint64        `json:"memory,omitempty"`
	CPU     float64       `json:"cpu,omitempty"`
	Unit    string        `json:"-"`
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
			Name: name,
		}
		m.refreshServiceLocked(info)
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
		m.refreshServiceLocked(svc)
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
	m.refreshServiceLocked(svc)
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
	m.refreshServiceLocked(svc)
	if !svc.Enabled || strings.TrimSpace(svc.Unit) == "" {
		return fmt.Errorf("service %s not configured/installed", name)
	}

	m.log.Infof("Executing %s on service %s", action, name)

	cmd := exec.Command("systemctl", action, svc.Unit)
	output, err := cmd.CombinedOutput()
	if err != nil {
		svc.Status = StatusError
		return fmt.Errorf("failed to %s %s: %s - %w", action, name, string(output), err)
	}

	m.refreshServiceLocked(svc)
	return nil
}

func (m *Manager) refreshServiceLocked(svc *ServiceInfo) {
	unit, installed := m.resolveServiceUnit(svc.Name)
	svc.Unit = unit
	svc.Enabled = installed
	if !installed {
		svc.Status = StatusUnknown
		return
	}
	svc.Status = m.checkServiceStatus(unit)
}

func (m *Manager) resolveServiceUnit(name string) (string, bool) {
	candidates := []string{name}
	switch name {
	case "php-fpm":
		candidates = []string{"php-fpm", "php8.4-fpm", "php8.3-fpm", "php8.2-fpm", "php8.1-fpm", "php8.0-fpm", "php7.4-fpm"}
	case "apache2":
		candidates = []string{"apache2", "httpd"}
	case "mysql":
		candidates = []string{"mysql", "mariadb"}
	case "redis-server":
		candidates = []string{"redis-server", "redis"}
	}
	for _, unit := range candidates {
		if m.isServiceInstalled(unit) {
			return unit, true
		}
	}
	switch name {
	case "php-fpm":
		if unit, ok := m.findInstalledUnit(func(u string) bool {
			return strings.HasPrefix(u, "php") && strings.HasSuffix(u, "-fpm")
		}); ok {
			return unit, true
		}
	case "postgresql":
		if unit, ok := m.findInstalledUnit(func(u string) bool {
			return u == "postgresql" || strings.HasPrefix(u, "postgresql@")
		}); ok {
			return unit, true
		}
	case "mysql":
		if unit, ok := m.findInstalledUnit(func(u string) bool {
			return u == "mysql" || u == "mariadb" || u == "mysqld"
		}); ok {
			return unit, true
		}
	case "redis-server":
		if unit, ok := m.findInstalledUnit(func(u string) bool {
			return u == "redis-server" || u == "redis" || strings.HasPrefix(u, "redis@")
		}); ok {
			return unit, true
		}
	case "apache2":
		if unit, ok := m.findInstalledUnit(func(u string) bool {
			return u == "apache2" || u == "httpd"
		}); ok {
			return unit, true
		}
	}
	return candidates[0], false
}

func (m *Manager) isServiceInstalled(name string) bool {
	unit := name + ".service"
	out, err := exec.Command("systemctl", "list-unit-files", "--type=service", unit).CombinedOutput()
	if err != nil {
		return false
	}
	return strings.Contains(string(out), unit)
}

func (m *Manager) findInstalledUnit(match func(string) bool) (string, bool) {
	units := m.listInstalledUnits()
	for _, u := range units {
		if match(u) {
			return u, true
		}
	}
	return "", false
}

func (m *Manager) listInstalledUnits() []string {
	out, err := exec.Command("systemctl", "list-unit-files", "--type=service", "--no-legend", "--no-pager").CombinedOutput()
	if err != nil {
		return nil
	}
	lines := strings.Split(string(out), "\n")
	units := make([]string, 0, len(lines))
	for _, ln := range lines {
		ln = strings.TrimSpace(ln)
		if ln == "" {
			continue
		}
		fields := strings.Fields(ln)
		if len(fields) == 0 {
			continue
		}
		unitFile := strings.TrimSpace(fields[0])
		if !strings.HasSuffix(unitFile, ".service") {
			continue
		}
		units = append(units, strings.TrimSuffix(unitFile, ".service"))
	}
	sort.Strings(units)
	return units
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
