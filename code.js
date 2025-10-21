// Function to open the Create Event popup
function openCreateEventPopup() {
    const popup = document.createElement('div');
    popup.id = 'create-event-popup';
    popup.style.position = 'fixed';
    popup.style.top = '0';
    popup.style.left = '0';
    popup.style.width = '100%';
    popup.style.height = '100%';
    popup.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    popup.style.display = 'flex';
    popup.style.justifyContent = 'center';
    popup.style.alignItems = 'center';
    popup.style.zIndex = '1000';

    const popupContent = document.createElement('div');
    popupContent.style.backgroundColor = '#fff';
    popupContent.style.padding = '20px';
    popupContent.style.borderRadius = '8px';
    popupContent.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
    popupContent.style.textAlign = 'center';
    popupContent.style.width = '300px';

    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = 'Enter event name';
    input.style.width = '100%';
    input.style.padding = '10px';
    input.style.marginBottom = '10px';
    input.style.border = '1px solid #ccc';
    input.style.borderRadius = '4px';

    const createButton = document.createElement('button');
    createButton.textContent = 'Create';
    createButton.style.marginRight = '10px';
    createButton.style.padding = '10px 20px';
    createButton.style.backgroundColor = '#007BFF';
    createButton.style.color = '#fff';
    createButton.style.border = 'none';
    createButton.style.borderRadius = '4px';
    createButton.style.cursor = 'pointer';

    const cancelButton = document.createElement('button');
    cancelButton.textContent = 'Cancel';
    cancelButton.style.padding = '10px 20px';
    cancelButton.style.backgroundColor = '#6c757d';
    cancelButton.style.color = '#fff';
    cancelButton.style.border = 'none';
    cancelButton.style.borderRadius = '4px';
    cancelButton.style.cursor = 'pointer';

    popupContent.appendChild(input);
    popupContent.appendChild(createButton);
    popupContent.appendChild(cancelButton);
    popup.appendChild(popupContent);
    document.body.appendChild(popup);

    cancelButton.addEventListener('click', () => {
        document.body.removeChild(popup);
    });

    createButton.addEventListener('click', () => {
        const eventName = input.value.trim();
        if (eventName) {
            createEvent(eventName);
            document.body.removeChild(popup);
        } else {
            alert('Please enter an event name.');
        }
    });
}

// Function to create an event in the database
function createEvent(eventName) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'LAMPAPI/CreateParty.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert('Event created successfully!');
                } else {
                    alert('Failed to create event: ' + response.error);
                }
            } else {
                alert('An error occurred while creating the event.');
            }
        }
    };

    const data = {
        name: eventName
    };

    xhr.send(JSON.stringify(data));
}

// Attach the openCreateEventPopup function to the Create Event button
document.querySelector('.btn').addEventListener('click', openCreateEventPopup);