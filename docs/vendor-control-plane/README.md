# Vendor Control Plane

Bu klasor, Panelsar'in musteri paneline ek olarak satisa hazir "merkezi yonetim" katmaninin (Vendor Panel) mimarisini ve uygulama planini tutar.

Hedef:
- Lisans, plan, musteri (tenant), node ve faturalama yonetimini tek merkezden yapmak
- Node tarafinda ozellikleri lisans durumuna gore ac/kapat
- Guvenlik, denetlenebilirlik (audit) ve operasyon surekliligi saglamak

Klasor icerigi:
- `ARCHITECTURE.md`: Teknik tasarim ve sistem sinirlari
- `ROADMAP.md`: Faz bazli yol haritasi
- `EXECUTION_CHECKLIST.md`: Gelistirme sirasinda adim adim kontrol listesi
- `PEN_TEST_CHECKLIST.md`: Canli oncesi guvenlik dogrulama listesi
- `BACKLOG.md`: Sonraki fazlar icin acik is listesi

Calisma prensibi:
1. Her yeni faz baslamadan once `ROADMAP.md` kapsamini netlestir.
2. Gelistirilen her adimi `EXECUTION_CHECKLIST.md` uzerinde isaretle.
3. Mimari degisikligi gerekiyorsa once `ARCHITECTURE.md` guncellenir, sonra kod degisikligine gecilir.
