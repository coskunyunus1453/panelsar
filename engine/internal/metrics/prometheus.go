package metrics

import (
	"sync"

	"github.com/gin-gonic/gin"
	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/collectors"
	"github.com/prometheus/client_golang/prometheus/promhttp"
)

var registerOnce sync.Once

// Init registers process and Go runtime collectors on the default registry (once).
func Init() {
	registerOnce.Do(func() {
		prometheus.MustRegister(collectors.NewProcessCollector(collectors.ProcessCollectorOpts{}))
		prometheus.MustRegister(collectors.NewGoCollector())
	})
}

// Handler returns a Gin handler that serves Prometheus metrics.
func Handler() gin.HandlerFunc {
	h := promhttp.Handler()
	return func(c *gin.Context) {
		h.ServeHTTP(c.Writer, c.Request)
	}
}
