package quota

import (
	"fmt"
	"sync"

	"github.com/sirupsen/logrus"
)

type ResourceType string

const (
	ResourceDisk    ResourceType = "disk"
	ResourceCPU     ResourceType = "cpu"
	ResourceMemory  ResourceType = "memory"
	ResourceBandwidth ResourceType = "bandwidth"
	ResourceDomains ResourceType = "domains"
	ResourceEmails  ResourceType = "emails"
	ResourceDBs     ResourceType = "databases"
)

type Quota struct {
	UserID   uint         `json:"user_id"`
	Resource ResourceType `json:"resource"`
	Limit    int64        `json:"limit"`
	Used     int64        `json:"used"`
	Unit     string       `json:"unit"`
}

type Manager struct {
	log    *logrus.Logger
	quotas map[string]*Quota
	mu     sync.RWMutex
}

func NewManager(log *logrus.Logger) *Manager {
	return &Manager{
		log:    log,
		quotas: make(map[string]*Quota),
	}
}

func (m *Manager) SetQuota(userID uint, resource ResourceType, limit int64, unit string) {
	m.mu.Lock()
	defer m.mu.Unlock()

	key := quotaKey(userID, resource)
	m.quotas[key] = &Quota{
		UserID:   userID,
		Resource: resource,
		Limit:    limit,
		Used:     0,
		Unit:     unit,
	}
}

func (m *Manager) CheckQuota(userID uint, resource ResourceType, requested int64) (bool, error) {
	m.mu.RLock()
	defer m.mu.RUnlock()

	key := quotaKey(userID, resource)
	q, ok := m.quotas[key]
	if !ok {
		return true, nil // No quota set = unlimited
	}

	if q.Limit == -1 {
		return true, nil // -1 = unlimited
	}

	if q.Used+requested > q.Limit {
		return false, fmt.Errorf("quota exceeded for %s: used=%d, limit=%d, requested=%d",
			resource, q.Used, q.Limit, requested)
	}

	return true, nil
}

func (m *Manager) UpdateUsage(userID uint, resource ResourceType, amount int64) {
	m.mu.Lock()
	defer m.mu.Unlock()

	key := quotaKey(userID, resource)
	if q, ok := m.quotas[key]; ok {
		q.Used += amount
		if q.Used < 0 {
			q.Used = 0
		}
	}
}

func (m *Manager) GetQuota(userID uint, resource ResourceType) (*Quota, error) {
	m.mu.RLock()
	defer m.mu.RUnlock()

	key := quotaKey(userID, resource)
	q, ok := m.quotas[key]
	if !ok {
		return nil, fmt.Errorf("no quota found for user %d resource %s", userID, resource)
	}
	return q, nil
}

func (m *Manager) GetAllQuotas(userID uint) []Quota {
	m.mu.RLock()
	defer m.mu.RUnlock()

	var result []Quota
	for _, q := range m.quotas {
		if q.UserID == userID {
			result = append(result, *q)
		}
	}
	return result
}

func quotaKey(userID uint, resource ResourceType) string {
	return fmt.Sprintf("%d:%s", userID, resource)
}
