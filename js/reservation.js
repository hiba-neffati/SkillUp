// SkillUp - Gestion dynamique des créneaux de réservation

const dateInput = document.getElementById('date');
const heureSelect = document.getElementById('heure');

if (dateInput) {
    dateInput.addEventListener('change', chargerCreneaux);
}

async function chargerCreneaux() {
    const date = dateInput.value;
    
    if (!date) {
        heureSelect.innerHTML = '<option value="">Sélectionnez une date d\'abord</option>';
        heureSelect.disabled = true;
        return;
    }
    
    try {
        const response = await fetch(`../api/get_creneaux.php?cours_id=${coursId}&date=${date}`);
        const data = await response.json();
        
        if (data.error) {
            heureSelect.innerHTML = '<option value="">Erreur</option>';
            return;
        }
        
        // Construire les options
        let html = '<option value="">Choisir un créneau...</option>';
        
        data.creneaux.forEach(creneau => {
            if (creneau.disponible) {
                html += `<option value="${creneau.heure}">${creneau.heure}</option>`;
            }
        });
        
        heureSelect.innerHTML = html;
        heureSelect.disabled = false;
        
    } catch (error) {
        console.error('Erreur:', error);
        heureSelect.innerHTML = '<option value="">Erreur de chargement</option>';
    }
}
