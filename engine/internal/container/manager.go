package container

import (
	"context"
	"fmt"
	"sync"

	"hostvim/engine/internal/config"
	"github.com/sirupsen/logrus"
)

type ContainerInfo struct {
	ID       string            `json:"id"`
	Name     string            `json:"name"`
	Image    string            `json:"image"`
	Status   string            `json:"status"`
	Ports    map[string]string `json:"ports"`
	Labels   map[string]string `json:"labels"`
	UserID   uint              `json:"user_id"`
	DomainID uint              `json:"domain_id"`
}

type ResourceLimits struct {
	CPUShares  int64  `json:"cpu_shares"`
	MemoryMB   int64  `json:"memory_mb"`
	DiskMB     int64  `json:"disk_mb"`
	IOWeight   uint16 `json:"io_weight"`
}

type Manager struct {
	cfg        *config.Config
	log        *logrus.Logger
	containers map[string]*ContainerInfo
	mu         sync.RWMutex
}

func NewManager(cfg *config.Config, log *logrus.Logger) *Manager {
	return &Manager{
		cfg:        cfg,
		log:        log,
		containers: make(map[string]*ContainerInfo),
	}
}

func (m *Manager) CreateContainer(ctx context.Context, domain string, userID uint, limits ResourceLimits) (*ContainerInfo, error) {
	m.mu.Lock()
	defer m.mu.Unlock()

	containerName := fmt.Sprintf("hostvim_%s", domain)

	m.log.Infof("Creating container %s for user %d", containerName, userID)

	container := &ContainerInfo{
		Name:   containerName,
		Image:  "hostvim/web:latest",
		Status: "created",
		Labels: map[string]string{
			"hostvim.domain":  domain,
			"hostvim.user_id": fmt.Sprintf("%d", userID),
		},
		UserID: userID,
	}

	m.containers[containerName] = container
	return container, nil
}

func (m *Manager) RemoveContainer(ctx context.Context, name string) error {
	m.mu.Lock()
	defer m.mu.Unlock()

	if _, ok := m.containers[name]; !ok {
		return fmt.Errorf("container %s not found", name)
	}

	delete(m.containers, name)
	m.log.Infof("Container %s removed", name)
	return nil
}

func (m *Manager) ListContainers() []ContainerInfo {
	m.mu.RLock()
	defer m.mu.RUnlock()

	result := make([]ContainerInfo, 0, len(m.containers))
	for _, c := range m.containers {
		result = append(result, *c)
	}
	return result
}

func (m *Manager) GetContainer(name string) (*ContainerInfo, error) {
	m.mu.RLock()
	defer m.mu.RUnlock()

	c, ok := m.containers[name]
	if !ok {
		return nil, fmt.Errorf("container %s not found", name)
	}
	return c, nil
}

func (m *Manager) UpdateLimits(ctx context.Context, name string, limits ResourceLimits) error {
	m.mu.Lock()
	defer m.mu.Unlock()

	if _, ok := m.containers[name]; !ok {
		return fmt.Errorf("container %s not found", name)
	}

	m.log.Infof("Updated resource limits for %s: CPU=%d, Mem=%dMB, Disk=%dMB",
		name, limits.CPUShares, limits.MemoryMB, limits.DiskMB)
	return nil
}
